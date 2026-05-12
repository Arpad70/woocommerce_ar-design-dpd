<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class DpdExpress1200ShippingMethod extends AbstractDpdCourierShippingMethod
{
    protected const METHOD_ID = 'wc_dpd_1200';
    protected const PRODUCT_CODE = 4;
    protected const METHOD_TITLE = 'DPD 12:00';
    protected const METHOD_DESCRIPTION = 'Courier delivery via DPD 12:00.';
    protected const DEFAULT_TITLE = 'DPD 12:00';
}
