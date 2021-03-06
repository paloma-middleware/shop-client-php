<?php

namespace Paloma\Shop\Common;

interface AddressInterface
{
    /**
     * @return string Localized address title, e.g. "Herr", "Ms"
     */
    function getTitle(): ?string;

    /**
     * @return string Address title code, one of 'mr', 'ms'
     */
    function getTitleCode(): ?string;

    function getFirstName(): ?string;

    function getLastName(): ?string;

    function getFullName(): ?string;

    function getCompany(): ?string;

    function getStreet(): ?string;

    function getZipCode(): ?string;

    function getCity(): ?string;

    function getCountry(): ?string;

    function getPhoneNumber(): ?string;

    function getEmailAddress(): ?string;

    function getRemarks(): ?string;
}