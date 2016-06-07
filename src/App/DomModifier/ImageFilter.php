<?php
namespace App\DomModifier;

/**
 * Append all scripts to the bottom of the body tag.
 * This is a current technique employed by designers
 * for mobile devices to load faster.
 *
 */
class ImageFilter extends \Dom\Modifier\Filter\Iface
{
    
    private $tmpPath = '';
    
    private $index = 0;
    
    
    /**
     * __construct
     *
     */
    public function __construct($tmpPath)
    {
        $this->tmpPath = $tmpPath;
        if (!is_readable($tmpPath)) {
            mkdir($tmpPath, 0777, true);
        }
    }



    /**
     * pre init the front controller
     *
     * @param \DOMDocument $doc
     */
    public function init($doc)
    {

    }


    /**
     * Call this method to traverse the node
     *
     * @param \DOMElement $node
     */
    public function executeNode(\DOMElement $node)
    {
        if ($node->nodeName == 'img') {
            $ext = $this->getExtension($node->getAttribute('src'));
            $img = file_get_contents($node->getAttribute('src'));
            $newPath = $this->tmpPath.'/'.$this->index.'.'.$ext;
            file_put_contents($newPath, $img);
            $node->setAttribute('src', $newPath);
            $this->index++;
        }
    }



    /**
     * called after DOM tree is traversed
     *
     * @param \DOMDocument $doc
     */
    public function postTraverse($doc) {
        
        
    }


    private function getExtension($file)
    {
        if (substr($file, -6) == 'tar.gz') {
            return 'tar.gz';
        }
        $pos = strrpos(basename($file), '.');
        if ($pos) {
            return substr(basename($file), $pos + 1);
        }
        return '';
    }
}
