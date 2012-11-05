<?php
/**
 * @author  M.Ozan Hazer
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 *
 */
class GoogleSitemapper
{
    public $namespaces = array(
        'default' => "http://www.sitemaps.org/schemas/sitemap/0.9",
        'image'   => "http://www.google.com/schemas/sitemap-image/1.1",
        'video'   => "http://www.google.com/schemas/sitemap-video/1.1",
        'mobile'  => "http://www.google.com/schemas/sitemap-mobile/1.0",
        'news'    => "http://www.google.com/schemas/sitemap-news/0.9"
    );

    const CHANGEFREQ_ALWAYS  = 'always';
    const CHANGEFREQ_HOURLY  = 'hourly';
    const CHANGEFREQ_DAILY   = 'daily';
    const CHANGEFREQ_WEEKLY  = 'weekly';
    const CHANGEFREQ_MONTHLY = 'monthly';
    const CHANGEFREQ_YEARLY  = 'yearly';
    const CHANGEFREQ_NEVER   = 'never';

    /**
     * @var string
     */
    protected $_siteAddress = '';

    /**
     * @var DOMElement
     */
    protected $_urlset;

    /**
     * @var DOMDocument
     */
    protected $_xmlDoc;

    /**
     * @var bool
     */
    protected $_autoEncodeUrl = true;

    /**
     * @var bool
     */
    protected $_autoSave = false;

    protected $_urlCount = 1;

    protected $_fileIndex = 1;

    protected $_gzip = true;

    protected $_sitemapFileName = 'sitemap';

    protected $_autoSavePerUrl = 50000;

    public function __construct($type = 'default')
    {
        $this->initialize();
    }

    public function initialize()
    {
        $this->_xmlDoc = new DOMDocument('1.0', 'utf-8');

        $urlset = $this->_xmlDoc->createElement('urlset');
        $urlset->setAttribute('xmlns', $this->namespaces['default']);

        $this->_urlset = $urlset;
    }

    public function createXml()
    {
        $xmlDoc = $this->_xmlDoc;

        $xmlDoc->appendChild($this->_urlset);

        $xmlDoc->formatOutput = true;
        return $xmlDoc->saveXML();
    }

    public function saveXML($gzip = null)
    {
        if ($this->_autoSave) {
            return;
        }

        $this->_saveXml($gzip);
    }

    protected function _saveXml($gzip = null)
    {
        if (is_null($gzip)) {
            $gzip = $this->_gzip;
        }

        $output    = $this->createXml();
        $extension = 'xml';

        if ($gzip) {
            if (!extension_loaded('zlib')) {
                throw new Exception('Zlib extension should be enabled for gzip support');
            }
            $output    = gzencode($output, 9);
            $extension = 'xml.gz';
        }

        $fileName = $this->getSitemapFileName();

        if ($this->_autoSave) {
            $fileName .= $this->_fileIndex;
        }

        file_put_contents($fileName . '.' . $extension, $output);
    }

    /**
     *
     * Valid formats for $lastmod:
     *  Complete date:
     *     YYYY-MM-DD (eg 1997-07-16)
     *  Complete date plus hours and minutes:
     *     YYYY-MM-DDThh:mmTZD (eg 1997-07-16T19:20+01:00 or 1997-07-16T18:20Z)
     *  Complete date plus hours, minutes and seconds:
     *     YYYY-MM-DDThh:mm:ssTZD (eg 1997-07-16T19:20:30+01:00)
     *  Complete date plus hours, minutes, seconds and a decimal fraction of a second
     *     YYYY-MM-DDThh:mm:ss.sTZD (eg 1997-07-16T19:20:30.45+01:00)
     *
     * where:
     *     YYYY = four-digit year
     *     MM   = two-digit month (01=January, etc.)
     *     DD   = two-digit day of month (01 through 31)
     *     hh   = two digits of hour (00 through 23) (am/pm NOT allowed)
     *     mm   = two digits of minute (00 through 59)
     *     ss   = two digits of second (00 through 59)
     *     s    = one or more digits representing a decimal fraction of a second
     *     TZD  = time zone designator (Z or +hh:mm or -hh:mm)
     *
     * -----
     * $image example:
     * array(
     *   'loc'          => 'http://www.somesite.com/images/image1.jpg',
     *   'caption'      => 'Some caption',
     *   'geo_location' => 'Some Location', // Limrick, Ireland for example
     *   'title'        => 'Some title',
     *   'license'      => 'http://www.licenseholder.com/license'
     * )
     *
     * @param string           $loc      Location of the page. Should be a full url or relative id $_siteAddress is set.
     * @param string           $changefreq
     * @param string|integer   $lastmod  ISO8601 formatted date or unix timestamp
     * @param string           $priority The importance of the page. Valid values: 0.0 to 1.0 (0.5 if not set)
     * @param array            $images   Images in the current page
     * @return DOMNode
     * @throws DomainException
     * @throws Exception
     */
    public function addUrl($loc, $changefreq = self::CHANGEFREQ_ALWAYS, $lastmod = null, $priority = null, $images = array())
    {
        if ($this->_autoSave) {
            if ($this->_urlCount % $this->_autoSavePerUrl == 0) {
                $this->_saveXml();
                $this->initialize();
                $this->_fileIndex++;
            }
            $this->_urlCount++;
        }

        $urlElement = $this->_xmlDoc->createElement('url');
        $url        = $this->_urlset->appendChild($urlElement);

        $locElement = $this->_xmlDoc->createElement('loc', $this->_filterUrl($loc));
        $url->appendChild($locElement);

        if ($lastmod) {
            $lastmod = $this->_filterDate($lastmod);

            $lastElement = $this->_xmlDoc->createElement('lastmod', $lastmod);
            $url->appendChild($lastElement);
        }

        if ($changefreq) {
            $changefreqElement = $this->_xmlDoc->createElement('changefreq', $changefreq);
            $url->appendChild($changefreqElement);
        }

        if ($priority) {
            $priorityElement = $this->_xmlDoc->createElement('priority', $priority);
            $url->appendChild($priorityElement);
        }

        return $url;
    }

    public function __destruct()
    {
        if ($this->_autoSave) {
            $this->_saveXml();
            $this->createIndex();
        }
    }

    protected function _filterDate($lastmod)
    {
        if (is_numeric($lastmod)) {
            $lastmod = date('c', $lastmod);
            return $lastmod;
        }
        return $lastmod;
    }

    /**
     * @param DOMNode    $url
     * @param array      $imageData
     * @throws DomainException
     */
    public function addImage($url, $imageData)
    {
        $this->_urlset->setAttribute('xmlns:image', $this->namespaces['image']);
        $imagesElement = $this->_xmlDoc->createElement('image:image');

        if (is_array($imageData)) {
            foreach ($imageData as $key => $val) {
                if (!in_array($key, array('loc', 'caption', 'geo_location', 'title', 'license'))) {
                    throw new DomainException('Invalid image value. ');
                }

                if ($key == 'loc') {
                    $val = $this->_filterUrl($val);
                }

                $element = $this->_xmlDoc->createElement('image:' . $key, $val);
                $imagesElement->appendChild($element);
            }
        } else {
            $imageLoc = $this->_xmlDoc->createElement('image:loc', $this->_filterUrl($imageData));
            $imagesElement->appendChild($imageLoc);
        }

        $url->appendChild($imagesElement);

    }

    protected function _encode($str)
    {
        list($path, $qStr) = explode('?', $str, 2);

        $paths = explode('/', $path);

        foreach ($paths as $key => $val) {
            $paths[$key] = rawurlencode($val);
        }

        $path = implode('/', $paths);

        if ($qStr) {
            parse_str($qStr, $qStr2);

            $qStr = array();
            foreach ($qStr2 as $key => $value) {
                $qStr[rawurlencode($key)] = rawurlencode($value);
            }

            unset($qStr2);
            $path .= '?' . http_build_query($qStr, '', '&amp;');

        }

        return $path;
    }

    /**
     * Add video.
     *
     * TODO: Better support for video. i.e. attributes for some tags
     *
     * @param $loc
     * @param $videoData
     * @throws DomainException
     */
    public function addVideo($loc, $videoData)
    {
        $urlElement = $this->_xmlDoc->createElement('url');
        $url        = $this->_urlset->appendChild($urlElement);

        $locElement = $this->_xmlDoc->createElement('loc', $this->_filterUrl($loc));
        $url->appendChild($locElement);

        $this->_urlset->setAttribute('xmlns:video', $this->namespaces['video']);
        $videosElement = $this->_xmlDoc->createElement('video:video');

        foreach ($videoData as $key => $val) {
            if (!in_array($key, array('thumbnail_loc', 'title', 'description', 'content_loc', 'player_loc',
                'duration', 'expiration_date', 'rating', 'view_count', 'publication_date', 'family_friendly',
                'tag', 'category', 'restriction', 'gallery_loc', 'price', 'requires_subscription', 'uploader',
                'platform', 'live'
            ))
            ) {
                throw new DomainException('Invalid video value.');
            }

            if (strpos($key, 'loc') !== false) {
                $val = $this->_filterUrl($val);
            }

            $element = $this->_xmlDoc->createElement('image:' . $key, $val);
            $videosElement->appendChild($element);
        }

        $url->appendChild($videosElement);

        // Required: loc, video:video, video:thumbnail_loc, video:title, video:description, video:content_loc or video:player_loc
        // Recommended: video:duration, video:expiration_date

//        <loc>http://www.example.com/videos/some_video_landing_page.html</loc>
//        <video:video>
//               <video:thumbnail_loc>http://www.example.com/thumbs/123.jpg</video:thumbnail_loc>
//               <video:title>Grilling steaks for summer</video:title>
//               <video:description>Alkis shows you how to get perfectly done steaks every
//                 time</video:description>
//               <video:content_loc>http://www.example.com/video123.flv</video:content_loc>
//               <video:player_loc allow_embed="yes" autoplay="ap=1">
//                 http://www.example.com/videoplayer.swf?video=123</video:player_loc>
//               <video:duration>600</video:duration>
//               <video:expiration_date>2009-11-05T19:20:30+08:00</video:expiration_date>
//               <video:rating>4.2</video:rating>
//               <video:view_count>12345</video:view_count>
//               <video:publication_date>2007-11-05T19:20:30+08:00</video:publication_date>
//               <video:family_friendly>yes</video:family_friendly>
//               <video:restriction relationship="allow">IE GB US CA</video:restriction>
//               <video:gallery_loc title="Cooking Videos">http://cooking.example.com</video:gallery_loc>
//               <video:price currency="EUR">1.99</video:price>
//               <video:requires_subscription>yes</video:requires_subscription>
//               <video:uploader info="http://www.example.com/users/grillymcgrillerson">GrillyMcGrillerson
//                 </video:uploader>
//               <video:live>no</video:live>
//             </video:video>
    }

    /**
     *
     */
    public function addNews()
    {
//        <loc>http://www.example.org/business/article55.html</loc>
//        <news:news>
//          <news:publication>
//            <news:name>The Example Times</news:name>
//            <news:language>en</news:language>
//          </news:publication>
//          <news:access>Subscription</news:access>
//          <news:genres>PressRelease, Blog</news:genres>
//          <news:publication_date>2008-12-23</news:publication_date>
//          <news:title>Companies A, B in Merger Talks</news:title>
//          <news:keywords>business, merger, acquisition, A, B</news:keywords>
//          <news:stock_tickers>NASDAQ:A, NASDAQ:B</news:stock_tickers>
//        </news:news>
    }

    public function createIndex()
    {
        $xmlDoc = new DOMDocument('1.0', 'utf-8');

        $sitemapindex = $xmlDoc->createElement('sitemapindex');
        $xmlDoc->appendChild($sitemapindex);

        $ext = 'xml';
        if ($this->_gzip) $ext .= '.gz';

        for ($i = 1; $i < $this->_fileIndex+1; $i++) {
            $sitemap = $xmlDoc->createElement('sitemap');
            $loc     = $xmlDoc->createElement('loc', $this->getSiteAddress() . '/sitemap' . $i . '.' . $ext);
            $lastmod = $xmlDoc->createElement('lastmod', date('c'));

            $sitemap->appendChild($loc);
            $sitemap->appendChild($lastmod);

            $sitemapindex->appendChild($sitemap);
        }

        $xmlDoc->formatOutput = true;
        $output = $xmlDoc->saveXML();

        if ($this->_gzip) {
            $output    = gzencode($output, 9);
        }

        file_put_contents($this->getSitemapFileName() . '.' . $ext, $output);
    }

    protected function _filterUrl($loc)
    {
        $isFullUrl = preg_match('#^(https?://)(.+)#', $loc, $m);
        if (!$this->getSiteAddress() and !$isFullUrl) {
            throw new Exception('Site address (url) not set. Either give full address or use \'setSiteAddress\' to set the default site url.');
        }

        if ($this->_autoEncodeUrl) {
            if ($isFullUrl) {
                list($address, $path) = explode('/', $m[2], 2);
                $loc = $m[1] . $address . '/' . $this->_encode($path);
            } else {
                $loc = ltrim($loc, '/');
                $loc = $this->_encode($loc);
            }
        }

        $loc = $isFullUrl ? $loc : $this->getSiteAddress() . '/' . $loc;

        return $loc;

    }

    public function enableAutoSave()
    {
        $this->_autoSave = true;

        return $this;
    }

    public function disableAutoSave()
    {
        $this->_autoSave = false;

        return $this;
    }

    public function enableCompression()
    {
        $this->_gzip = true;

        return $this;
    }

    public function disableCompression()
    {
        $this->_gzip = false;

        return $this;
    }

    public function disableAutoEncodeUrl()
    {
        $this->_autoEncodeUrl = false;

        return $this;
    }

    public function enableAutoEncodeUrl()
    {
        $this->_autoEncodeUrl = true;

        return $this;
    }

    public function setSiteAddress($siteAddress)
    {
        $siteAddress = rtrim($siteAddress, '/');

        if (substr($siteAddress, 0, 4) != 'http') {
            throw new Exception('Invalid address. The address should start with http or https');
        }

        $this->_siteAddress = $siteAddress;

        return $this;
    }

    public function getSiteAddress()
    {
        return $this->_siteAddress;
    }

    public function getSitemapFileName()
    {
        return $this->_sitemapFileName;
    }

    public function setSitemapFileName($sitemapFileName)
    {
        $this->_sitemapFileName = $sitemapFileName;

        return $this;
    }

    public function getAutoSavePerUrl()
    {
        return $this->_autoSavePerUrl;
    }

    public function setAutoSavePerUrl($autoSavePerUrl)
    {
        $this->_autoSavePerUrl = $autoSavePerUrl;

        return $this;
    }
}
