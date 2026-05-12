<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

class DpdGuaranteeShippingMethod extends AbstractDpdCourierShippingMethod
{
    protected const METHOD_ID = 'wc_dpd_guarantee';
    protected const PRODUCT_CODE = 2;
    protected const METHOD_TITLE = 'DPD 18:00 / DPD Guarantee';
    protected const METHOD_DESCRIPTION = 'Courier delivery via DPD 18:00 / DPD Guarantee.';
    protected const DEFAULT_TITLE = 'DPD 18:00 / DPD Guarantee';
}
