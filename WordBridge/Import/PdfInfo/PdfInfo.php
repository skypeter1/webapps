<?php
/**
 * Gather meta information on PDF files, this class wraps the pdfinfo utility built on xpdf 3.0
 * To install on the mac, first install xQuartz, then install xpdf via homebrew or macports
 *
 * @author cwalker
 */
class PdfInfo 
{    
    /**
     * gather meta data on $pdfFile as return in assoc array:
     * Creator
     * Producer
     * CreationDate
     * ModDate
     * Tagged
     * Form
     * Pages
     * Encrypted
     * Page size
     * File size
     * Optimized
     * PDF version
     * additonal values:
     * dirname
     * extension
     * basename
     * Page width inches
     * Page width pts
     * Page height inches
     * page height pts
     * @param string $pdfFile
     * @return array
     */
    public static function getInfo($pdfFile)            
    {
        $pdfData = array();
        if(file_exists($pdfFile))
        {
            $pdfData = pathinfo($pdfFile);
            $binary = Configure::read("processor.pdfinfo");
            if(!strlen($binary))
            {
                CakeLog::debug("[PdfInfo::getInfo] Fully qualified path to pdfinfo executable is not specified in \$config['processor']");
                $binary = "pdfinfo";
            }
            $cmd = $binary." ".$pdfFile; 
            exec($cmd, $output,$exitCode);
            if($exitCode==0)
            {
                foreach($output as $line)
                {
                    $parts = explode(':',$line,2);
                    $pdfData[trim($parts[0])] = trim($parts[1]);
                } 
                $dimensions = $pdfData["Page size"];
                if(strlen($dimensions)>0)
                {
                    $dims = explode(' ',$dimensions);
                    $width = (int)$dims[0];
                    if($width)
                    {
                        $pdfData["Page width inches"] = $width / 2.83 ;
                        $pdfData["Page width pts"] = $width;
                    }
                    $height = (int)$dims[2];
                    if($height)
                    {
                        $pdfData["Page height inches"] = $height / 2.83 ;
                        $pdfData["Page height pts"] = $height;
                    }                    
                }
            }
            else
            {
                throw new Exception("[PdfInfo::getInfo] Error running PDF Info $cmd: ".implode($output));
            }
        }
        else
        {
            throw new Exception("[PdfInfo::getInfo] PDF File $pdfFile not found");
        }
        return $pdfData;
    }    
}

?>
