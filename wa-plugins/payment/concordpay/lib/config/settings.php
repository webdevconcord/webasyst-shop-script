<?php
return array(
    'merchant_account' => array(
        'value'        => '',
        'title'        => _w('Merchant ID'),
        'description'  => _w('Merchant ID in the ConcordPay system'),
        'control_type' => waHtmlControl::INPUT,
    ),
    'secret_key' => array(
        'value'        => '',
        'title'        => _w('Secret key'),
        'description'  => _w('Secret key in the ConcordPay system'),
        'control_type' => waHtmlControl::INPUT,
    ),
);