<?php
/**
 * Created by PhpStorm.
 * User: tonchik™
 * Date: 15.09.2015
 * Time: 19:18
 */

namespace TonchikTm\PdfToHtml;

use DOMDocument;
use DOMXPath;
use Pelago\Emogrifier;
use tidy;

/**
 * This class creates a collection of html pages with some improvements.
 *
 * @property integer $pages
 * @property string[] $content
 */
class Html extends Base
{
    private $pages = 0;
    private $content = [];

    private $defaultOptions = [
        'inlineCss' => true,
        'inlineImages' => true,
        'onlyContent' => false,
        'outputDir' => ''
    ];

    public function __construct($options=[])
    {
        $this->setOptions(array_replace_recursive($this->defaultOptions, $options));
    }

    /**
     * Add page to collection with the conversion, according to options.
     * @param integer $number
     * @param string $content
     * @return $this
     */
    public function addPage($number, $content)
    {
        if ($this->getOptions('inlineCss')) {
            $content = $this->setInlineCss($content);
        }

        if ($this->getOptions('inlineImages')) {
            $content = $this->setInlineImages($content);
        }

        if ($this->getOptions('removeFontFamily')) {
            $content = $this->removeFontFamily($content);
        }

        if ($this->getOptions('changeLinks')) {
            $content = $this->setLocalRefs($content);
        }

        if ($this->getOptions('onlyContent')) {
            $content = $this->setOnlyContent($content);
        }

        $this->content[$number] = $content;
        $this->pages = count($this->content[$number]);
        return $this;
    }

    /**
     * @param $number
     * @return string|null
     */
    public function getPage($number)
    {
        return isset($this->content[$number]) ? $this->content[$number] : null;
    }

    /**
     * @return array
     */
    public function getAllPages()
    {
        return $this->content;
    }

    /**
     * The method replaces css class to inline css rules.
     * @param $content
     * @return string
     */
    private function setInlineCss($content)
    {
        $content = str_replace(['<!--', '-->'], '', $content);
        $parser = new Emogrifier($content);
        return $parser->emogrify();
    }

    /**
     * The method looks for images in html and replaces the src attribute to base64 hash.
     * @param string $content
     * @return string
     */
    private function setInlineImages($content)
    {

        $dom = new DOMDocument();
        $dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace("xml", "http://www.w3.org/1999/xhtml");

        $images = $xpath->query("//img");
        foreach ($images as $img) { /** @var \DOMNode $img  */
            $attrImage = $img->getAttribute('src');
            $pi = pathinfo($attrImage);
            $image = $this->getOutputDir() . '/' . urldecode($pi['basename']);
            $imageData = base64_encode(file_get_contents($image));
            $src = 'data: ' . mime_content_type($image) . ';base64,' . $imageData;
            $content = str_replace($attrImage, $src, $content);
        }
        unset($dom, $xpath, $images, $imageData);
        return $content;
    }

    /**
     * The method looks for refs and replaces the href attribute.
     * @param string $content
     * @return string
     */
    public function setLocalRefs($content)
    {
        $dom = new DOMDocument();
        $dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace("xml", "http://www.w3.org/1999/xhtml");

        $refs = $xpath->query("//a");

        foreach ($refs as $ref) { /** @var \DOMNode $img  */
            $href = $ref->getAttribute('href');
            $ref->setAttribute('class', 'linkToPage');
            if (strpos($href, $this->getOutputDir()) !== false) {
                $filenameExt = substr($href, strrpos($href, '/'));
                $filename = pathinfo($filenameExt, PATHINFO_FILENAME);
                $page = substr($filename, strrpos($filename, '-') + 1);
                $ref->setAttribute('href', '#'.$page);
            }
        }
        $content = $dom->saveHtml();
        unset($dom, $xpath, $refs, $imageData);
        return $content;
    }

    /**
     * The method remove all font-families from styles.
     * @param string $content
     * @return string
     */
    private function removeFontFamily($content)
    {
        return preg_replace('/font-family:.*?;/', '', $content);
    }

    /**
     * The method takes from html body content only.
     * @param string $content
     * @return string
     */
    private function setOnlyContent($content)
    {
        $dom = new DOMDocument();
        $dom->loadHTML($content);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace("xml", "http://www.w3.org/1999/xhtml");

        $html = '';
        $body = $xpath->query("//body")->item(0);
        foreach($body->childNodes as $node) {
            $html .= $dom->saveHTML($node);
        }
        unset($dom, $xpath, $body, $content);
        return trim($html);
    }
}