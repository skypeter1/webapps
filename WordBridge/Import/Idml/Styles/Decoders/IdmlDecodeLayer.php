<?php
/**
 * @package /app/Import/Idml/Styles/Decoders/IdmlDecodeLayer.php
 *
 * @class   IdmlDecodeLayer
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

App::uses('IdmlDecode',       'Import/Idml/Decoders');
App::uses('IdmlLayerManager', 'Import/Idml');


class IdmlDecodeLayer extends IdmlDecode
{
    public function convert()
    {
        $layerId = (array_key_exists('ItemLayer', $this->idmlContext)) ? $this->idmlContext['ItemLayer'] : null;

        if ($layerId) {

            $layerMgr = IdmlLayerManager::getInstance();

            $layer = $layerMgr->getLayer($layerId);
            $zIndex = $layer['zIndex'];
            $visible = $layer['visible'];

            $this->registerCSS('z-index', $zIndex);

            if ($visible == 'false')
            {
                $this->registerCSS('display', 'none');
            }

            // Set required position property, but only if it is not already set.
            if ($this->propertyIsSet('position'))
            {
                $this->registerCSS('position', 'relative');
            }
        }
    }
}
?>