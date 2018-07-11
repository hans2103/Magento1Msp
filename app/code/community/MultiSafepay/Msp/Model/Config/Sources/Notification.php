<?php

/**
 *
 * @category MultiSafepay
 * @package  MultiSafepay_Msp
 */
class MultiSafepay_Msp_Model_Config_Sources_Notification
{

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                "value" => "push",
                "label" => "Push"
            ),
            array(
                "value" => "pull",
                "label" => "Pull"
            )
        );
    }

}
