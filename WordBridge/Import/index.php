<html>
<title>Test Library</title>

<body>

<h1>Test Library</h1>

<p>This is a test library of docx to HTML conversion</p>

<?php

    include_once 'Word/XWPFToHTMLConverter.php';

    //Set document directory
    $progress = "";
    $workingDir = "/home/peter/Documents/";

    $sourceFile = "/home/peter/Documents/multilevelList.docx";

    // Init Apache POI
    $converter = new XWPFToHTMLConverter($workingDir, $progress);
    if (!$converter) {
        throw new Exception(
            '[WordProcessor::routine] '
            .'Book ID ' . $this->bookId . ' cannot be processed as a working directory cannot be found.'
        );
    }

    // Set docx file to parse
    $converter->setDocFileToParse($sourceFile);

    // Convert everything to HTML
    $converter->convertToHTML();

    // Get HTML pages
    $pages = $converter->getHTMLPages();
    //$this->progress->adjustMaxSteps((count($pages) * 2) + count($this->sourceAssets)+1);
    foreach($pages as $key => $page) {
        //$pages[$key]->setStyleInline(false);
        $pageContents[] = $pages[$key]->getBodyHTML();
        echo $pages[$key]->getBodyHTML();

    }

    // Get CSS
    echo "<style>";
    echo $converter->mainStyleSheet->getPagesCSS();
    echo "</style>";

    // Save image assets
    //$this->createImageAssets();



?>

</body>
</html>