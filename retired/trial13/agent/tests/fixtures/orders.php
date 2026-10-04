<?php
// Anonymised production rows (support ticket FIN-3301).
return [
    'shop' => [
        'S-10001' => ['number' => 'S-10001', 'channel' => 'web', 'shipping' => 490,
            'lines' => [['sku' => 'BOX-S', 'qty' => 4, 'unit' => 125], ['sku' => 'BOX-L', 'qty' => 1, 'unit' => 349, 'discount' => 49]]],
    ],
    'legacy' => [
        'W-88812' => ['order_no' => 'W-88812', 'freight' => 4500,
            'positions' => [['article' => 'PAL-EURO', 'qty' => 3, 'total' => 100000], ['article' => 'BOX-L', 'qty' => 10, 'total' => 3490]]],
    ],
];
