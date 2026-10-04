<?php
declare(strict_types=1);
namespace App\Customers;

final class Customer
{
    public int $id;
    public string $name;
    public string $street;
    public string $postcode;
    public string $city;
    public int $feeCents;
    public string $currency;

    public function __construct(int $id, string $name, string $street, string $postcode, string $city, int $feeCents, string $currency = 'EUR')
    {
        $this->id = $id;
        $this->name = $name;
        $this->street = $street;
        $this->postcode = $postcode;
        $this->city = $city;
        $this->feeCents = $feeCents;
        $this->currency = $currency;
    }
}
