<?php
require_once "lib/classes/IActivityProvider.php";
require_once "lib/FitbitProvider.php";
require_once "lib/HuaweiProvider.php";

class ActivityProvider {
    const PROVIDER_HUAWEI = 'huawei';
    const PROVIDER_FITBIT = 'fitbit';

    /**
     *
     * @return IActivityProvider
     */
    static public function getInstance($service) {
        if ($service == self::PROVIDER_FITBIT) {
            return new FitbitProvider();
        }
        if ($service == self::PROVIDER_HUAWEI) {
            return new HuaweiProvider();
        }

        // default
        return new HuaweiProvider();
    }
}
?>