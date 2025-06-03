<?php

namespace Paloma\Shop\Customers;

use DateTime;
use Paloma\Shop\Common\Address;
use Paloma\Shop\Common\AddressInterface;
use Paloma\Shop\Common\MetadataContainingObject;
use Paloma\Shop\Security\UserDetailsInterface;

class Customer extends CustomerBasics implements CustomerInterface, MetadataContainingObject
{
    public static function toBackendData(CustomerInterface $customer, UserDetailsInterface $user = null)
    {
        return [
            'id' => $customer->getId(),
            'emailAddress' => $customer->getEmailAddress(),
            'customerNumber' => $customer->getCustomerNumber(),
            'locale' => $customer->getLocale(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'company' => $customer->getCompany(),
            'gender' => $customer->getGender(),
            'dateOfBirth' => $customer->getDateOfBirth() == null ? null : $customer->getDateOfBirth()->format('Y-m-d'),
            'contactAddress' => Address::toAddressArray($customer->getContactAddress()),
            'billingAddress' => Address::toAddressArray($customer->getBillingAddress()),
            'shippingAddress' => Address::toAddressArray($customer->getShippingAddress()),
            'users' => $user
                ? ['id' => $user->getUserId()]
                : null,
            'priceGroups' => $customer->getPriceGroups(),
        ];
    }

    function getContactAddress(): ?AddressInterface
    {
        return Address::ofData($this->data['contactAddress']);
    }

    function getBillingAddress(): ?AddressInterface
    {
        return Address::ofData($this->data['billingAddress']);
    }

    function getShippingAddress(): ?AddressInterface
    {
        return Address::ofData($this->data['shippingAddress']);
    }

    function getMetaValidation()
    {
        if (!isset($this->data['_validation'])) {
            return null;
        }

        return $this->data['_validation'];
    }

    function getPriceGroups(): array
    {
        return $this->data['priceGroups'] ?? [];
    }

    /**
     * @inheritDoc
     */
    function getCustomData(): array
    {
        return $this->data['customData'] ?? [];
    }
}