<?php
declare(strict_types=1);
namespace App\Cart;

final class Address
{
    public string $country;
    public string $postcode;

    public function __construct(string $country, string $postcode)
    {
        $this->country = $country;
        $this->postcode = $postcode;
    }
}
