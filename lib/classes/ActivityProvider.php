<?php
require_once "lib/classes/IActivityProvider.php";
require_once "lib/FitbitProvider.php";
require_once "lib/HuaweiProvider.php";

class ActivityProvider {
    const PROVIDER_HUAWEI = 'huawei';
    const PROVIDER_FITBIT = 'fitbit';

    /**
     *
     * @param string $providerName
     * @return IActivityProvider
     */
    static public function getInstance($providerName) {
        $providerName = strtolower($providerName);
        if ($providerName == self::PROVIDER_FITBIT) {
            return new FitbitProvider();
        }
        if ($providerName == self::PROVIDER_HUAWEI) {
            return new HuaweiProvider();
        }

        // default
        return new FitbitProvider();
    }
}
?>