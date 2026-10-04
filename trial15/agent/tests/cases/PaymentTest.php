<?php
declare(strict_types=1);

test('successful card payment returns the transaction id', function (): void {
    $gateway = new App\Payments\Gateways\StripeGateway(function (array $request): array {
        return ['status' => 'succeeded', 'id' => 'ch_1'];
    });
    assertSame('ch_1', $gateway->charge(1000, 'tok'));
});
