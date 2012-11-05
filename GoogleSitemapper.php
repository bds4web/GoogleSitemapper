<?php
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

    private $_siteAddress;

    private $_urlset;

    private $_xmlDoc;

    private $_autoEncodeUrl = true;

    public function setSiteAddress($siteAddress)
    {
        $siteAddress        = rtrim($siteAddress, '/');

        if(substr($siteAddress, 0, 4) != 'http') {
            throw new Exception('Invalid address. The address should start with http or https');
        }

        $this->_siteAddress = $siteAddress;
    }

    public function getSiteAddress()
    {
        return $this->_siteAddress;
    }

    public function disableAutoEncodeUrl ($value) 
    {
        $this->_autoEncodeUrl = false;
    }

    public function enableAutoEncodeUrl ($value) 
    {
        $this->_autoEncodeUrl = true;
    }

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

    public function saveXML($gzip = true)
    {
        $output = $this->createXml();
        $extension = 'xml';

        if($gzip) {
            $output = gzencode($output, 9);
            $extension = 'xml.gz';
        }

        file_put_contents('sitemap.' . $extension, $output);
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
     */
    public function addUrl($loc, $changefreq = self::CHANGEFREQ_ALWAYS, $lastmod = null, $priority = null, $images = array())
    {
        if(!$this->getSiteAddress()) {
            throw new Exception('Site address not set. Please use \'setSiteAddress\' to set it');
        }

        $urlElement = $this->_xmlDoc->createElement('url');
        $url = $this->_urlset->appendChild($urlElement);

        $loc = ltrim($loc, '/');

        if($this->_autoEncodeUrl) {
            $loc = $this->_encode($loc);
        }

        $locElement = $this->_xmlDoc->createElement('loc', $this->getSiteAddress() . '/' . $loc);
        $url->appendChild($locElement);

        if($lastmod) {
            $lastElement = $this->_xmlDoc->createElement('lastmod', $lastmod);
            $url->appendChild($lastElement);
        }

        if($changefreq) {
            $changefreqElement = $this->_xmlDoc->createElement('changefreq', $changefreq);
            $url->appendChild($changefreqElement);
        }

        if($priority) {
            $priorityElement = $this->_xmlDoc->createElement('priority', $priority);
            $url->appendChild($priorityElement);
        }        

        if (count($images) > 0) {
            $this->_urlset->setAttribute('xmlns:image', $this->namespaces['image']);
            $imagesElement = $this->_xmlDoc->createElement('image:image');

            foreach ($images as $img) {
                if(is_array($img)) {
                    foreach($img as $key => $val) {
                        if(!in_array($key, array('loc', 'caption', 'geo_location', 'title', 'license'))) {
                            throw new DomainException('Invalid image value. The array souhld be an associated array');
                        }

                        $element = $this->_xmlDoc->createElement('image:' . $key, $val);
                        $imagesElement->appendChild($element);
                    }
                } else {
                    $imageLoc = $this->_xmlDoc->createElement('image:loc', $img);
                    $imagesElement->appendChild($imageLoc);
                }
            }
        }
    }

    private function _encode($str) 
    {
        list($path, $qStr) = explode('?', $str, 2);

        $path = rawurlencode($path);

        if($qStr) {
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

    public function addVideo()
    {
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
}

$sitemap = new GoogleSitemap();
$sitemap->setSiteAddress('http://www.uzmansorusu.com');
// $sitemap->addUrl('/questions');

for($i = 0; $i < 100; $i++) {
    $sitemap->addUrl('/überşaft' . uniqid() . '?şakacı=1&deneme=2<test>"\'?test_question mark');
}


//echo $sitemap->createXml();
$sitemap->saveXml(false);
// $sitemap->saveXml();

echo memory_get_peak_usage(true) . "\n";
echo "Bitti\n";
