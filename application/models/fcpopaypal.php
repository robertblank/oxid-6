<?php
/** 
 * PAYONE OXID Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PAYONE OXID Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with PAYONE OXID Connector.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */
 
class fcpopaypal extends oxBase
{

    /**
     * Collects messages of different types
     *
     * @var array
     */
    protected $_aAdminMessages = array();

    /**
     * Helper object for dealing with different shop versions
     *
     * @var fcpohelper
     */
    protected $_oFcpoHelper = null;

    /**
     * Centralized Database instance
     *
     * @var object
     */
    protected $_oFcpoDb = null;

    /**
     * Path of payone images
     *
     * @var string
     */
    protected $_sPayPalExpressLogoPath = 'modules/fc/fcpayone/out/img/';

    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew('fcpohelper');
        $this->_oFcpoDb = oxDb::getDb();
    }

    /**
     * Method returns collected messages back to controller
     *
     * @param  void
     * @return array
     */
    public function fcpoGetMessages()
    {
        return $this->_aAdminMessages;
    }

    /**
     * Method requests database for fetching paypal logos and return this data in an array
     *
     * @param  void
     * @return array
     */
    public function fcpoGetPayPalLogos()
    {
        $sQuery = "SELECT oxid, fcpo_active, fcpo_langid, fcpo_logo, fcpo_default FROM fcpopayoneexpresslogos";
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        $aRows = $oDb->getAll($sQuery);
        $aLogos = array();

        foreach ($aRows as $aRow) {
            $sOxid = $aRow[0];
            $sPoActive = $aRow[1];
            $sPoLangId = $aRow[2];
            $sPoLogo = $aRow[3];
            $sPoDefault = $aRow[4];

            $aLogo = array();
            $aLogo['oxid'] = $sOxid;
            $aLogo['active'] = (bool) $sPoActive;
            $aLogo['langid'] = $sPoLangId;
            $aLogo['logo'] = '';

            $aLogo = $this->_fcpoAddLogoPath($sPoLogo, $aLogo);
            $aLogo['default'] = (bool) $sPoDefault;
            $aLogos[] = $aLogo;
        }

        return $aLogos;
    }

    /**
     * Add logo path if dependencies are fulfilled
     *
     * @param  string $sPoLogo
     * @param  array  $aLogo
     * @return array
     */
    protected function _fcpoAddLogoPath($sPoLogo, $aLogo)
    {
        $blLogoEnteredAndExisting = $this->_fcpoGetLogoEnteredAndExisting($sPoLogo);
        if ($blLogoEnteredAndExisting) {
            $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
            $sShopURL = $oConfig->getCurrentShopUrl(false);
            $aLogo['logo'] = $sShopURL . $this->_sPayPalExpressLogoPath . $sPoLogo;
        }

        return $aLogo;
    }

    /**
     * Updates a given set of logos into database
     *
     * @param  array $aLogos
     * @return void
     */
    public function fcpoUpdatePayPalLogos($aLogos)
    {
        foreach ($aLogos as $iId => $aLogo) {
            $oDb = $this->_oFcpoHelper->fcpoGetDb();
            $sLogoQuery = $this->_handleUploadPaypalExpressLogo($iId);

            $sQuery = " UPDATE
                                fcpopayoneexpresslogos
                            SET
                                FCPO_ACTIVE = " . oxDb::getDb()->quote($aLogo['active']) . ",
                                FCPO_LANGID = " . oxDb::getDb()->quote($aLogo['langid']) . "
                                {$sLogoQuery}
                            WHERE
                                oxid = " . oxDb::getDb()->quote($iId);

            $oDb->Execute($sQuery);
            $this->_fcpoTriggerUpdateLogos();
        }
    }

    /**
     * Do the update on database
     *
     * @param  void
     * @return void
     */
    protected function _fcpoTriggerUpdateLogos()
    {
        $iDefault = $this->_oFcpoHelper->fcpoGetRequestParameter('defaultlogo');
        if ($iDefault) {
            $sQuery = "UPDATE fcpopayoneexpresslogos SET fcpo_default = 0";
            $this->_oFcpoDb->Execute($sQuery);

            $sQuery = "UPDATE fcpopayoneexpresslogos SET fcpo_default = 1 WHERE oxid = " . oxDb::getDb()->quote($iDefault);
            $this->_oFcpoDb->Execute($sQuery);
        }
    }

    /**
     * Add a new empty paypal-logo entry into database
     *
     * @param  void
     * @return void
     */
    public function fcpoAddPaypalExpressLogo()
    {
        $sQuery = "INSERT INTO fcpopayoneexpresslogos (FCPO_ACTIVE, FCPO_LANGID, FCPO_LOGO, FCPO_DEFAULT) VALUES (0, 0, '', 0)";
        $this->_oFcpoDb->Execute($sQuery);
    }

    /**
     * Validates the existance and availablility of paypalexpress logo
     *
     * @param  string $sPoLogo
     * @return bool
     */
    protected function _fcpoGetLogoEnteredAndExisting($sPoLogo)
    {
        $blValid = (
                !empty($sPoLogo) &&
                $this->_oFcpoHelper->fcpoFileExists(getShopBasePath() . $this->_sPayPalExpressLogoPath . $sPoLogo)
                );

        return $blValid;
    }

    /**
     * Handle the uploading of paypal logos
     *
     * @param  int $iId
     * @return string
     */
    protected function _handleUploadPaypalExpressLogo($iId)
    {
        $sLogoQuery = '';
        $aFiles = $this->_oFcpoHelper->fcpoGetFiles();

        $blFileValid = $this->_fcpoValidateFile($iId, $aFiles);
        if ($blFileValid) {
            // $sFilename = $aFiles['logo_' . $iId]['name'];
            $sLogoQuery = $this->_fcpoHandleFile($iId, $aFiles);
        }

        return $sLogoQuery;
    }

    /**
     * Handles the upload file
     *
     * @param int   $iId
     * @param array $aFiles
     * @return string
     */
    protected function _fcpoHandleFile($iId, $aFiles)
    {
        $sLogoQuery = '';

        $sMediaUrl = $this->_fcpoFetchMediaUrl($iId, $aFiles);

        if ($sMediaUrl) {
            $sLogoQuery = ", FCPO_LOGO = " . oxDb::getDb()->quote(basename($sMediaUrl));
            $this->_aAdminMessages["blLogoAdded"] = true;
        }

        return $sLogoQuery;
    }

    /**
     * Grabs the media url form data and returns it
     *
     * @param int   $iId
     * @param array $aFiles
     */
    protected function _fcpoFetchMediaUrl($iId, $aFiles)
    {
        $oUtilsFile = $this->_oFcpoHelper->fcpoGetUtilsFile();
        if ($this->_oFcpoHelper->fcpoGetIntShopVersion() < 4530) {
            $sMediaUrl = $oUtilsFile->handleUploadedFile($aFiles['logo_' . $iId], $this->_sPayPalExpressLogoPath);
        } else {
            $sMediaUrl = $oUtilsFile->processFile('logo_' . $iId, $this->_sPayPalExpressLogoPath);
        }

        return $sMediaUrl;
    }

    /**
     * Method checks if all needed data of file is available
     *
     * @param  int   $iId
     * @param  array $aFiles
     * @return bool
     */
    protected function _fcpoValidateFile($iId, $aFiles) 
    {
        $blReturn = (
                $aFiles &&
                array_key_exists('logo_' . $iId, $aFiles) !== false &&
                $aFiles['logo_' . $iId]['error'] == 0
                );

        return $blReturn;
    }

}
