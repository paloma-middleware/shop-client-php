<?php

namespace Paloma\Shop\Checkout;

interface CartInterface
{
    /**
     * @return CartItemInterface[]
     */
    function getItems(): array;

    /**
     * @return int Cart items count
     */
    function getItemsCount(): int;

    /**
     * @return int Number of cart items times quantities
     */
    function getUnitsCount(): int;

    /**
     * @return bool True if the cart contains no items
     */
    function isEmpty(): bool;

    /**
     * @return string
     */
    function getItemsPrice(): string;

    /**
     * @return string
     */
    function getNetItemsPrice(): string;

    /**
     * If an operation requires modification to the cart that where not explicitly requested by the client,
     * those modifications are listed here. Example: Item was removed because it is no longer available
     * @return CartModificationInterface[]
     */
    function getModifications(): array;
}