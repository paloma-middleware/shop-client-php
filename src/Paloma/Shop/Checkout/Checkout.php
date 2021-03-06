<?php

namespace Paloma\Shop\Checkout;

use DateTime;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
use Paloma\Shop\Common\Address;
use Paloma\Shop\Common\AddressInterface;
use Paloma\Shop\Customers\Customer;
use Paloma\Shop\Customers\CustomerInterface;
use Paloma\Shop\Error\BackendUnavailable;
use Paloma\Shop\Error\CartIsEmpty;
use Paloma\Shop\Error\CartItemNotFound;
use Paloma\Shop\Error\InvalidCouponCode;
use Paloma\Shop\Error\InvalidInput;
use Paloma\Shop\Error\InvalidShippingTargetDate;
use Paloma\Shop\Error\NonElectronicPaymentMethod;
use Paloma\Shop\Error\OrderNotReadyForCouponCodes;
use Paloma\Shop\Error\OrderNotReadyForFinalization;
use Paloma\Shop\Error\OrderNotReadyForPayment;
use Paloma\Shop\Error\OrderNotReadyForPurchase;
use Paloma\Shop\Error\ProductVariantNotFound;
use Paloma\Shop\Error\ProductVariantUnavailable;
use Paloma\Shop\Error\UnknownPaymentMethod;
use Paloma\Shop\Error\UnknownShippingMethod;
use Paloma\Shop\PalomaClientInterface;
use Paloma\Shop\Security\UserDetailsInterface;
use Paloma\Shop\Security\PalomaSecurityInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Checkout implements CheckoutInterface
{
    /**
     * @var PalomaClientInterface
     */
    private $client;

    /**
     * @var PalomaSecurityInterface
     */
    private $userProvider;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var LoggerInterface
     */
    private $log;

    public function __construct(PalomaClientInterface $client,
                                PalomaSecurityInterface $userProvider,
                                ValidatorInterface $validator,
                                LoggerInterface $log = null)
    {
        $this->client = $client;
        $this->userProvider = $userProvider;
        $this->validator = $validator;
        $this->log = $log === null ? new NullLogger() : $log;
    }

    function getCart(): CartInterface
    {
        try {

            $checkoutOrder = $this->getCheckoutOrder();

            return $checkoutOrder->existsInSession()
                ? new Cart($checkoutOrder->get())
                : Cart::createEmpty();

        } catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {

                $this->log->warning('Cart order not found');

                return Cart::createEmpty();
            }
            throw BackendUnavailable::ofException($bre);
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function addCartItem(string $sku, int $quantity = 1): CartInterface
    {
        try {

            $data = $this->getCheckoutOrder()->addItem($sku, $quantity);

            return new Cart($data);

        } catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductVariantNotFound();
            }
            throw new ProductVariantUnavailable();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function updateCartItem(string $itemId, int $quantity): CartInterface
    {
        if ($quantity <= 0) {
            return $this->removeCartItem($itemId);
        }

        try {

            $data = $this->getCheckoutOrder()->updateQuantity($itemId, $quantity);

            return new Cart($data);

        } catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new CartItemNotFound();
            }
            throw new ProductVariantUnavailable();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function removeCartItem(string $itemId): CartInterface
    {
        try {

            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                return Cart::createEmpty();
            }

            $data = $checkoutOrder->removeItem($itemId);

            return new Cart($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getOrderDraft(): OrderDraftInterface
    {
        try {

            $checkoutOrder = $this->getCheckoutOrder();

            if ($checkoutOrder->itemsCount() === 0) {
                throw new CartIsEmpty();
            }

            $data = $checkoutOrder->get();

            return new OrderDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function setCustomer(CustomerInterface $customer, UserDetailsInterface $user = null): OrderDraftInterface
    {
        try {

            $data = $this->getCheckoutOrder()->setCustomer(Customer::toBackendData($customer, $user));

            return new OrderDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function setAddresses(AddressInterface $billingAddress, AddressInterface $shippingAddress = null): OrderDraftInterface
    {
        $validation = $this->validator->validate([
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
        ]);

        if ($validation->count() > 0) {
            throw InvalidInput::ofValidation($validation);
        }

        try {

            $data = $this->getCheckoutOrder()->setAddresses(
                $this->toAddressArray($billingAddress),
                $this->toAddressArray($shippingAddress)
            );

            return new OrderDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function setBillingAddress(AddressInterface $address): OrderDraftInterface
    {
        $validation = $this->validator->validate($address);

        if ($validation->count() > 0) {
            throw InvalidInput::ofValidation($validation);
        }

        try {

            $data = $this->getCheckoutOrder()->setAddresses(
                $this->toAddressArray($address),
                null
            );

            return new OrderDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function setShippingAddress(AddressInterface $address): OrderDraftInterface
    {
        $validation = $this->validator->validate($address);

        if ($validation->count() > 0) {
            throw InvalidInput::ofValidation($validation);
        }

        try {

            $data = $this->getCheckoutOrder()->setAddresses(
                null,
                $this->toAddressArray($address)
            );

            return new OrderDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    /**
     * @return ShippingMethodInterface[]
     * @throws BackendUnavailable
     */
    function getShippingMethods(): array
    {
        try {

            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                return [];
            }

            $orderData = $checkoutOrder->get();

            $data = $this->getCheckout()->getShippingMethods($orderData['id']);

            return array_map(function($elem) use ($orderData) {
                return ShippingMethod::ofDataAndOrder($elem, $orderData);
            }, $data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getShippingMethodOptions(string $shippingMethod, DateTime $from = null, DateTime $until = null): ShippingMethodOptionsInterface
    {
        try {

            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                return new ShippingMethodOptions([]);
            }

            $data = $this->getCheckout()->getShippingMethodOptions(
                $checkoutOrder->get()['id'],
                $from ? $from->format('Y-m-d') : null,
                $until ? $until->format('Y-m-d') : null
            );

            return new ShippingMethodOptions($data);

        } catch (BadResponseException $be) {
            if ($be->getCode() === 404) {
                throw new UnknownShippingMethod();
            }
            throw new InvalidInput(); // TODO more info
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function setShippingMethod(string $shippingMethod, DateTime $targetDate = null): OrderDraftInterface
    {
        try {

            $orderData = $this->getCheckoutOrder()->get();

            $data = $this->getCheckout()->setShippingMethod($orderData['id'], [
                'name' => $shippingMethod,
                'targetDate' => $targetDate
                    ? $targetDate->format('Y-m-d')
                    : null,
            ]);

            return new OrderDraft($data);

        } catch (BadResponseException $be) {
            if ($be->getCode() === 404 || $targetDate == null) {
                throw new UnknownShippingMethod();
            }
            throw new InvalidShippingTargetDate();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    /**
     * @return PaymentMethodInterface[]
     * @throws BackendUnavailable
     */
    function getPaymentMethods(): array
    {
        try {

            $checkoutOrder = $this->getCheckoutOrder();

            $orderData = $checkoutOrder->get();

            $data = $checkoutOrder->getPaymentMethods();

            return array_map(function($elem) use ($orderData) {
                return PaymentMethod::ofDataAndOrder($elem, $orderData);
            }, $data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function setPaymentMethod(string $paymentMethod, ?string $paymentInstrument = null): OrderDraftInterface
    {
        try {

            $data = $this->getCheckoutOrder()->setPaymentMethod([
                'name' => $paymentMethod,
                'paymentInstrumentId' => $paymentInstrument,
            ]);

            return new OrderDraft($data);

        } catch (BadResponseException $be) {
            throw new UnknownPaymentMethod();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function finalize(): OrderDraftInterface
    {
        try {
            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                throw new OrderNotReadyForFinalization();
            }
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }

        $orderData = $checkoutOrder->get();

        try {

            $data = $this->getCheckout()->finalizeOrder($orderData['id']);

            return new OrderDraft($data);

        } catch (BadResponseException $bre) {
            throw new OrderNotReadyForFinalization();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function addCouponCode(string $couponCode): OrderDraftInterface
    {
        try {
            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                throw new OrderNotReadyForCouponCodes();
            }
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }

        $orderData = $checkoutOrder->get();

        if ($orderData['status'] !== 'finalized') {
            throw new OrderNotReadyForCouponCodes();
        }

        try {

            $data = $this->getCheckout()->addCoupon($orderData['id'], ['code' => $couponCode]);

            return new OrderDraft($data);

        } catch (BadResponseException $be) {
            throw InvalidCouponCode::ofHttpResponse($be->getResponse());
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function removeCouponCode(String $couponCode): OrderDraftInterface
    {
        try {

            $orderData = $this->getCheckoutOrder()->get();

            $data = $this->getCheckout()->deleteCoupon($orderData['id'], $couponCode);

            return new OrderDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function initializePayment(PaymentInitParametersInterface $params): PaymentDraftInterface
    {
        try {
            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                throw new OrderNotReadyForPayment();
            }
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }

        $orderData = $checkoutOrder->get();
        if (!isset($orderData['paymentMethod']['type']) || $orderData['paymentMethod']['type'] !== 'electronic') {
            throw new NonElectronicPaymentMethod();
        }

        if ($orderData['status'] !== 'finalized') {
            throw new OrderNotReadyForPayment();
        }

        try {

            $data = $checkoutOrder->initPayment([
                'successUrl' => $params->getSuccessUrl(),
                'cancelUrl' => $params->getCancelUrl(),
                'errorUrl' => $params->getErrorUrl(),
            ]);

            return new PaymentDraft($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function purchase(): OrderPurchaseInterface
    {
        try {
            $checkoutOrder = $this->getCheckoutOrder();
            if (!$checkoutOrder->existsInSession()) {
                throw new OrderNotReadyForPurchase();
            }
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }

        $orderData = $checkoutOrder->get();

        if ($orderData['status'] !== 'finalized') {
            throw new OrderNotReadyForPurchase();
        }

        try {

            $data = $checkoutOrder->purchase();

            return new OrderPurchase($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    private function toAddressArray(?AddressInterface $address)
    {
        return Address::toAddressArray($address);
    }

    private function getCheckoutOrder(): CheckoutOrder
    {
        $customer = $this->userProvider->getCustomer();
        $user = $this->userProvider->getUser();

        return $this->getCheckout()->checkoutOrder($customer, $user);
    }

    private function getCheckout(): CheckoutClientInterface
    {
        return $this->client->checkout();
    }
}