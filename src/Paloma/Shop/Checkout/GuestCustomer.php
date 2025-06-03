<?php

namespace Paloma\Shop\Checkout;

use DateTime;
use Paloma\Shop\Common\AddressInterface;
use Paloma\Shop\Customers\CustomerInterface;

class GuestCustomer implements CustomerInterface
{
    private $emailAddress;

    private $locale;

    private $firstName;

    private $lastName;

    private $company;

    private $gender;

    private $dateOfBirth;

    public function __construct(
        string $emailAddress = null,
        string $locale = null,
        string $firstName = null,
        string $lastName = null,
        string $company = null,
        string $gender = 'unknown',
        DateTime $dateOfBirth = null)
    {
        $this->emailAddress = $emailAddress;
        $this->locale = $locale;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->company = $company;
        $this->gender = $gender;
        $this->dateOfBirth = $dateOfBirth;
    }

    function withLocale($locale): GuestCustomer
    {
        return new GuestCustomer(
            $this->emailAddress,
            $locale,
            $this->firstName,
            $this->lastName,
            $this->company,
            $this->gender,
            $this->dateOfBirth
        );
    }

    function getId(): ?string
    {
        return null;
    }

    function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    function getCustomerNumber(): ?string
    {
        return null;
    }

    function getLocale(): string
    {
        return $this->locale;
    }

    function getFirstName(): ?string
    {
        return $this->firstName;
    }

    function getLastName(): ?string
    {
        return $this->lastName;
    }

    function getCompany(): ?string
    {
        return $this->company;
    }

    function getGender(): string
    {
        return $this->gender;
    }

    function getDateOfBirth(): ?DateTime
    {
        return $this->dateOfBirth;
    }

    function getContactAddress(): ?AddressInterface
    {
        return null;
    }

    function getBillingAddress(): ?AddressInterface
    {
        return null;
    }

    function getShippingAddress(): ?AddressInterface
    {
        return null;
    }

    function getPriceGroups(): array
    {
        return [];
    }

    function getCustomData(): array
    {
        return [];
    }
}
