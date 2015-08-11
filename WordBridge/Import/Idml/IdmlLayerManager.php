<?php
/**
 * @package /app/Lib/Import/Idml/PageElements/IdmlLayerManager.php
 *
 * @class   IdmlLayerManager
 *
 * @description The manager for Layers
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

class IdmlLayerManager
{
    /**
     * $layers is an array of layer id->level information.
     * For each layer, the key is the source identifier, and the value is the level at which the layer is displayed.
     * Levels are converted to z-index for HTML; higher levels are displayed on top of lower levels in the final HTML.
     * The array is populated during the parsing of the design map, where InDesign stores the data,
     *   and retrieved during the production of HTML.
     * @var array $layers
     */
    protected $layers;

    private $zIndex;

    private static $instance = null;

    public static function getInstance()
    {
        if (IdmlLayerManager::$instance == null)
            IdmlLayerManager::$instance = new IdmlLayerManager();

        return IdmlLayerManager::$instance;
    }

    private function __construct()
    {
        $this->layers = array();
        $this->zIndex = 0;
    }

    /**
     * Add a layer to the array.
     * Array key is UID (from IDML), and value is a sequence number which is incremented in the process.
     * @param DOMNode $layer
     */
    public function addLayer(DOMNode $layer)
    {
        // Get layer attributes: id and visibility
        $idAttrib = $layer->attributes->getNamedItem('Self');
        $id = $idAttrib->value;

        $visibleAttrib = $layer->attributes->getNamedItem('Visible');
        $visible = $visibleAttrib->value;

        // Add attribute values to $layers array
        $this->layers[$id]['zIndex'] = $this->zIndex;
        $this->layers[$id]['visible'] = $visible;

        // Increment z-index value
        $this->zIndex++;
    }

    /**
     * Returns the z-index value of the layer identified by the parameter
     * @param $id
     * @return mixed
     */
    public function getLayer($id)
    {
        return $this->layers[$id];
    }
}