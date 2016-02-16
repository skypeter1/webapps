<?php

/**
 * Created by PhpStorm.
 * User: Peter
 * Date: 9/2/16
 * Time: 12:49 PM
 */
class XWPFPicture
{
    private $unsupportedImageFormats = array('emf' => 'emf', 'wmf' => 'wmf');
    private $picture;
    private $mainStyleSheet;
    private $images_currentIndex;
    private $images;
    private $images_data;
    private $_tmp_path;

    /**
     * @param $picture
     * @param $mainStyleSheet
     * @param $path
     */
    function __construct($picture, $mainStyleSheet, $path)
    {
        if (java_instanceof($picture, java('org.apache.poi.xwpf.usermodel.XWPFPicture'))) {
            $this->picture = $picture;
        }
        $this->mainStyleSheet = $mainStyleSheet;
        $this->_tmp_path = $path;
    }

    /**
     * Parses a picture element of HWPF document
     * @param   object
     * @return  HTMLElement
     */
    public function processPicture()
    {
        // Get picture data
        $pictureData = $this->picture->getPictureData();
        $picContent = java_values($pictureData->getData());
        $picName = java_values($pictureData->getFileName());
        $picExtension = java_values($pictureData->suggestFileExtension());

        if (array_key_exists($picExtension, $this->unsupportedImageFormats)) {
            $message = $this->returnUnssuportedImageMessage($picName);
            return $message;
        }

        // Get picture dimensions
        //$ct_xml = java_values($picture->getCTPicture()->toString());
        $ct_xml = $this->getCTPicture();
        $xml = new SimpleXMLElement($ct_xml);
        $picExt = $xml->xpath('/xml-fragment/pic:spPr/a:xfrm/a:ext');
        $picExt = $picExt[0];
        $cx = $picExt['cx'];
        $cy = $picExt['cy'];
        $widthInch = round(intval($cx) / HWPFWrapper::EMUS_PER_INCH, 4);
        $heightInch = round(intval($cy) / HWPFWrapper::EMUS_PER_INCH, 4);

        // Set ALT data
        $alt = '';
        if (isset($this->images_data[$this->images_currentIndex])) {
            $imageData = $this->images_data[$this->images_currentIndex];
            if (strlen($imageData['title']) > 0) {
                $alt = $imageData['title'];
                if (strlen($imageData['descr']) > 0) {
                    $alt .= ': ' . $imageData['descr'];
                }
            } else if (strlen($imageData['descr']) > 0) {
                $alt = $imageData['descr'];
            }
        }

        // Adjust current image index
        $this->images_currentIndex++;

        // Create path to image
        $path = $this->_tmp_path . '/images/' . $picName;
        $dirname = dirname($path);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0775, true);
        }

        // Store picture in a tmp dir
        $fp = fopen($path, 'w');
        fwrite($fp, $picContent);
        fclose($fp);
        $this->images[$picName] = $picName;

        // Create fake ID
        $fakeId = strtolower(str_replace(' ', '', $picName)) . '_' . $this->images_currentIndex;

        // Creating img element with path to newly created picture
        $imageContainer = new HTMLElement(HTMLElement::IMG);
        $imageContainer->setAttribute("style", "width: {$widthInch}in; height: {$heightInch}in; display: initial");
        $imageContainer->setAttribute("src", '../images/' . $picName);
        $imageContainer->setAttribute("data-ch-file-id", "{$picName}");
        $imageContainer->setAttribute("alt", $alt);
        $imageContainer->setAttribute("title", $alt);
        $imageContainer->setAttribute("id", 'image_' . $fakeId);

        //Add Special markup
        $container = new HTMLElement(HTMLElement::FIGURE);
        $container->addInnerElement($imageContainer);

        // Return image container
        return $container;
    }

    /**
     * @param $name
     * @return HTMLElement
     */
    private function returnUnssuportedImageMessage($name)
    {
        $messageBox = new HTMLElement(HTMLElement::DIV);
        $boxStyleClass = new StyleClass();

        $messageText = "This image could not be imported. It's not supported format.";
        $messageParagraph = new HTMLElement(HTMLElement::P);
        $messageParagraph->setInnerText($messageText);
        $messageParagraph->setAttribute('style', "font-weight: bold");
        $messageBox->addInnerElement($messageParagraph);

        $imageName = (string)$name;
        $nameParagraph = new HTMLElement(HTMLElement::P);
        $nameParagraph->setInnerText($imageName);
        $messageBox->addInnerElement($nameParagraph);

        $boxStyleClass->setAttribute('width', '75%');
        $boxStyleClass->setAttribute('margin', '0 auto');
        $boxStyleClass->setAttribute('background-color', '#ffcfba');
        $boxStyleClass->setAttribute('color', '#d91c24');
        $boxStyleClass->setAttribute('font-size', '14px');
        $boxStyleClass->setAttribute('font-family', 'Roboto, sans-serif');
        $boxStyleClass->setAttribute('padding-bottom', '25px');
        $boxStyleClass->setAttribute('padding-top', '25px');
        $boxStyleClass->setAttribute('padding-right', '25px');
        $boxStyleClass->setAttribute('padding-left', '25px');
        $boxStyleClass->setAttribute('text-align', 'center');

        $className = $this->mainStyleSheet->getClassName($boxStyleClass);
        $messageBox->setClass($className);

        return $messageBox;
    }

    /**
     * @return mixed
     */
    private function getCTPicture()
    {
        $data = $this->picture->getCTPicture()->toString();
        return $data;
    }

}