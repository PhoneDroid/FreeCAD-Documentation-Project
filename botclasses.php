<?php
/**
 * botclasses.php - Bot classes for interacting with mediawiki.
 *
 *  (c) 2008-2012 Chris G - https://en.wikipedia.org/wiki/User:Chris_G
 *  (c) 2009-2010 Fale - https://en.wikipedia.org/wiki/User:Fale
 *  (c) 2010      Kaldari - https://en.wikipedia.org/wiki/User:Kaldari
 *  (c) 2011      Gutza - https://en.wikipedia.org/wiki/User:Gutza
 *  (c) 2012      Sean - https://en.wikipedia.org/wiki/User:SColombo
 *  (c) 2012      Brian - https://en.wikipedia.org/wiki/User:Brian_McNeil
 *  (c) 2012-2018 Pavel Malahov - https://en.wikipedia.org/wiki/User:24pm
 *  (c) 2020-2025 Bill - https://en.wikipedia.org/wiki/User:wbm1058
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *  Developers (add yourself here if you worked on the code):
 *      Cobi    - [[User:Cobi]]         - Wrote the http class and some of the wikipedia class
 *      Chris   - [[User:Chris_G]]      - Wrote the most of the wikipedia class
 *      Fale    - [[User:Fale]]         - Polish, wrote the extended and some of the wikipedia class
 *      Kaldari - [[User:Kaldari]]      - Submitted a patch for the imagematches function
 *      Gutza   - [[User:Gutza]]        - Submitted a patch for http->setHTTPcreds(), and http->quiet
 *      Sean    - [[User:SColombo]]     - Wrote the lyricwiki class (now moved to lyricswiki.php)
 *      Brian   - [[User:Brian_McNeil]] - Wrote wikipedia->getfileuploader() and wikipedia->getfilelocation
 *      Pavel	- [[User:24pm]]         - Wrote 10 new functions for class "extended"
 *      Bill    - [[User:wbm1058]]      - Wrote wikipedia-> categories, getTalkTransclusions, recent_page_edits, ten_latest_edits, oldest_revision, getpagetitle,
 *                                        and getOldestDeletedRevisionTimestamp
 *      Furkan  - [[User:Orfur]]        - Adapting the upload function to current PHP versions, Added Pavel Malahov's changes
 **/

/*
 * Forks/Alternative versions:
 * There's a couple of different versions of this code lying around.
 * I'll try to list them here for reference purpopses:
 *		https://raw.githubusercontent.com/legoktm/harej-bots/master/botclasses.php
 * 		https://en.wikinews.org/wiki/User:NewsieBot/botclasses.php
 * 		https://raw.githubusercontent.com/teopedia/mediawiki-botclasses/refs/heads/master/botclasses.php
 */

/**
 * Global hacks :<
 * @author Legoktm
 */
$AssumeHTTPFailuresAreJustTimeoutsAndShouldBeSuppressed = false;

/**
 * This class is designed to provide a simplified interface to cURL which maintains cookies.
 * @author Cobi
 **/
class http {
    private $ch;
    private $uid;
    public $cookie_jar;
    public $postfollowredirs;
    public $getfollowredirs;
    public $quiet=false;
    public $useragent;

	public function http_code () {
        return curl_getinfo( $this->ch, CURLINFO_HTTP_CODE );
	}

    function data_encode ($data, $keyprefix = "", $keypostfix = "") {
        assert( is_array($data) );
        $vars=null;
        foreach($data as $key=>$value) {
            if(is_array($value)) {
                $vars .= $this->data_encode($value, $keyprefix.$key.$keypostfix.urlencode("["), urlencode("]"));
            } else {
                $vars .= $keyprefix.$key.$keypostfix."=".urlencode($value)."&";
            }
        }
        return $vars;
    }

    function __construct () {
        $this->ch = curl_init();
        $this->uid = dechex(rand(0,99999999));
        curl_setopt($this->ch,CURLOPT_COOKIEJAR,'/tmp/cluewikibot.cookies.'.$this->uid.'.dat');
        curl_setopt($this->ch,CURLOPT_COOKIEFILE,'/tmp/cluewikibot.cookies.'.$this->uid.'.dat');
        curl_setopt($this->ch,CURLOPT_MAXCONNECTS,100);
        $this->postfollowredirs = 0;
        $this->getfollowredirs = 1;
        $this->cookie_jar = array();
    }

    function post ($url,$data) {
        //echo 'POST: '.$url."\n";
        $time = microtime(1);
        curl_setopt($this->ch,CURLOPT_URL,$url);
        curl_setopt($this->ch,CURLOPT_USERAGENT,$this->useragent);
        /* Crappy hack to add extra cookies, should be cleaned up */
        $cookies = null;
        foreach ($this->cookie_jar as $name => $value) {
            if (empty($cookies)) {
                $cookies = "$name=$value";
            } else {
                $cookies .= "; $name=$value";
            }
        }
        if ($cookies != null) {
            curl_setopt($this->ch,CURLOPT_COOKIE,$cookies);
        }
        curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$this->postfollowredirs);
        curl_setopt($this->ch,CURLOPT_MAXREDIRS,10);
        curl_setopt($this->ch,CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($this->ch,CURLOPT_TIMEOUT,30);
        curl_setopt($this->ch,CURLOPT_CONNECTTIMEOUT,10);
        curl_setopt($this->ch,CURLOPT_POST,1);
        //      curl_setopt($this->ch,CURLOPT_FAILONERROR,1);
        //	curl_setopt($this->ch,CURLOPT_POSTFIELDS, substr($this->data_encode($data), 0, -1) );
        curl_setopt($this->ch,CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($this->ch);
        if($data === false) {
            echo "cURL Error: ".curl_error($this->ch)."\n";
        }
        //	var_dump($data);
        //	global $logfd;
        //	if (!is_resource($logfd)) {
        //		$logfd = fopen('php://stderr','w');
        if (!$this->quiet) {
            echo 'POST: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n";
        }
        // 	}
        return $data;
    }

    function get ($url) {
        //echo 'GET: '.$url."\n";
        $time = microtime(1);
        curl_setopt($this->ch,CURLOPT_URL,$url);
        curl_setopt($this->ch,CURLOPT_USERAGENT,$this->useragent);
        /* Crappy hack to add extra cookies, should be cleaned up */
        $cookies = null;
        foreach ($this->cookie_jar as $name => $value) {
            if (empty($cookies)) {
                $cookies = "$name=$value";
            } else {
                $cookies .= "; $name=$value";
            }
        }
        if ($cookies != null) {
            curl_setopt($this->ch,CURLOPT_COOKIE,$cookies);
        }
        curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$this->getfollowredirs);
        curl_setopt($this->ch,CURLOPT_MAXREDIRS,10);
        curl_setopt($this->ch,CURLOPT_HEADER,0);
        curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($this->ch,CURLOPT_TIMEOUT,30);
        curl_setopt($this->ch,CURLOPT_CONNECTTIMEOUT,10);
        curl_setopt($this->ch,CURLOPT_HTTPGET,1);
        //curl_setopt($this->ch,CURLOPT_FAILONERROR,1);
        $data = curl_exec($this->ch);
        if($data === false) {
            echo "cURL error: ".curl_error($this->ch)."\n";
        }
        //var_dump($data);
        //global $logfd;
        //if (!is_resource($logfd)) {
        //    $logfd = fopen('php://stderr','w');
        if (!$this->quiet) {
            echo 'GET: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n";
        }
        //}
        return $data;
    }

    function setHTTPcreds($uname,$pwd) {
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->ch, CURLOPT_USERPWD, $uname.":".$pwd);
    }

    function __destruct () {
        curl_close($this->ch);
        @unlink('/tmp/cluewikibot.cookies.'.$this->uid.'.dat');
    }
}

/**
 * This class is interacts with wikipedia using api.php
 * @author Chris G and Cobi
 **/
class wikipedia {
    public $http;
    private $token;
    private $ecTimestamp;
    public $url;

    /**
     * This is our constructor.
     * @return void
     **/
    function __construct ($url='https://en.wikipedia.org/w/api.php',$hu=null,$hp=null) {
        $this->http = new http;
        if ($this->http->useragent==null) {
            $this->http->useragent = 'php wikibot classes [[User:RMCD bot/botclasses.php]]';
        }
        $this->token = null;
        $this->url = $url;
        $this->ecTimestamp = null;
        if ($hu!==null) {
            $this->http->setHTTPcreds($hu,$hp);
        }
    }

    function __set($var,$val) {
        switch($var) {
            case 'quiet':
                $this->http->quiet=$val;
                break;
            default:
                echo "WARNING: Unknown variable ($var)!\n";
        }
    }

    /**
     * Sends a query to the api.
     * @param $query string The query string.
     * @param $post string POST data if its a post request (optional).
     * @param $repeat int how many times we've repeated this request
     * @return array The api result.
     * @throws Exception on HTTP errors
     **/
    function query ($query,$post=null,$repeat=0) {
        global $AssumeHTTPFailuresAreJustTimeoutsAndShouldBeSuppressed;
        if ($post==null) {
            $ret = $this->http->get($this->url.$query);
        } else {
            $ret = $this->http->post($this->url.$query,$post);
        }
        //var_dump($this->http->http_code());
	    if ($this->http->http_code() == 0 && $AssumeHTTPFailuresAreJustTimeoutsAndShouldBeSuppressed) {
            return array(); // Meh
	    }
		if ($this->http->http_code() != "200") {
            if ($repeat < 10) {
                sleep(10);
                echo "\n *** Retry query, attempt " . $repeat . " ***\n";
                return $this->query($query,$post,++$repeat);
            } else {
                throw new Exception("HTTP Error.");
            }
		}
        return json_decode($ret, true);
    }

    /**
     * Gets the content of a page. Returns false on error.
     * @param $page The wikipedia page to fetch.
     * @param $revid The revision id to fetch (optional)
     * @return string|false The wikitext for the page.
     **/
    function getpage ($page,$revid=null,$detectEditConflict=false,&$timestamp=null,&$user=null) {
        $append = '';
        if ($revid!=null) {
            $append = '&rvstartid='.$revid;
        }
        $x = $this->query('?action=query&format=json&prop=revisions&rvslots=main&titles='.urlencode($page).'&rvlimit=1&rvprop=content|timestamp|user'.$append);
        #print_r($x);
        foreach ($x['query']['pages'] as $ret) {
            if (isset($ret['revisions'][0]['slots']['main']['*'])) {
                if ($detectEditConflict) {
                    $this->ecTimestamp = $ret['revisions'][0]['timestamp'];
                }
                $timestamp = $ret['revisions'][0]['timestamp'];
                $user = $ret['revisions'][0]['user'];
                return $ret['revisions'][0]['slots']['main']['*'];
            } else {
                return false;
            }
        }
    }

    /**
     * Gets up to nine recent edits to a page by a specified editor. Used to prevent bots from edit warring.
     * @param $page The wikipedia page to fetch.
     * @param $editor The editor's edits to fetch (usually the bot's user ID).
     * @return int The number of recent edits the user has made to the page (up to 9). "Recent edits" are edits made in the past day (24 hours).
     **/
    function recent_page_edits ($page,$editor) {
        $ds = 86400;    #number of seconds in a day
        $timestamp = date(DATE_ISO8601, time() - $ds);
        #echo $timestamp . "\n";
        $x = $this->query('?action=query&format=json&prop=revisions&rvslots=main&titles='.urlencode($page).'&rvuser='.urlencode($editor).
            '&rvend='.urlencode($timestamp).'&rvlimit=9&rvprop=user|timestamp|comment');
        #print_r($x);
        foreach ($x['query']['pages'] as $ret) {
            #print_r($ret);
            if (isset($ret['revisions'])) {
                #print_r($ret['revisions']);
                return count($ret['revisions']);
            } else {
                return 0;
            }
        }
    }

    /**
     * Gets the ten latest edits to a page. Used to find history that blocks page-movers from moving.
     * @param $page The wikipedia page to fetch.
     * @return int The number of page-edits found (up to 10).
     **/
    function ten_latest_edits ($page) {
        $x = $this->query('?action=query&format=json&prop=revisions&rvslots=main&titles='.urlencode($page).'&rvlimit=10&rvprop=user|timestamp|comment');
        #print_r($x);
        foreach ($x['query']['pages'] as $ret) {
            #print_r($ret);
            if (isset($ret['revisions'])) {
                #print_r($ret['revisions']);
                return count($ret['revisions']);
            } else {
                return 0;
            }
        }
    }

    /**
     * Returns true if $revid is the oldest revision of $page
     **/
    function oldest_revision ($page,$revid) {
        $x = $this->query('?action=query&format=json&prop=revisions&titles='.urlencode($page).'&rvlimit=2&rvstartid='.$revid.'&rvdir=older');
        foreach ($x['query']['pages'] as $ret) {
            if (isset($ret['revisions'][1]['revid'])) {
                return false;
            } else
                return true;
        }
    }

    /**
     * Gets the page id for a page.
     * @param $page The wikipedia page to get the id for.
     * @return int The page id of the page.
     **/
    function getpageid ($page) {
        $x = $this->query('?action=query&format=json&prop=revisions&titles='.urlencode($page).'&rvlimit=1&rvprop=content');
        foreach ($x['query']['pages'] as $ret) {
            return $ret['pageid'];
        }
    }

    /**
     * Gets the page title for a revision.
     * @param $revid The revision id to get the title for.
     * @return The title of the page.
     **/
    function getpagetitle ($revid) {
        $x = $this->query('?action=query&format=json&revids='.$revid);
        foreach ($x['query']['pages'] as $ret) {
            return $ret['title'];
        }
    }

    /**
     * Gets the number of contributions a user has.
     * @param $user The username for which to get the edit count.
     * @return int The number of contributions the user has.
     **/
    function contribcount ($user) {
        $x = $this->query('?action=query&list=allusers&format=json&auprop=editcount&aulimit=1&aufrom='.urlencode($user));
        return $x['query']['allusers'][0]['editcount'];
    }

    /**
     * Returns an array with all the categories $page belongs to
     * @param $page
     * @return array
     **/
    function categories ($page) {
        $x = $this->query('?action=query&format=json&prop=categories&titles='.urlencode($page));
        foreach ($x['query']['pages'] as $ret) {
            return $ret['categories'];
        }
    }

    /**
     * Returns an array with all the members of $category
     * @param $category The category to use.
     * @param $subcat (bool) Go into sub categories?
     * @return array
     **/
    function categorymembers ($category,$subcat=false) {
        $continue = '&rawcontinue=';
        $pages = array();
        while (true) {
            $res = $this->query('?action=query&list=categorymembers&cmtitle='.urlencode($category).'&format=json&cmlimit=500'.$continue);
            if (isset($x['error'])) {
                return false;
            }
            foreach ($res['query']['categorymembers'] as $x) {
                $pages[] = $x['title'];
            }
            if (empty($res['query-continue']['categorymembers']['cmcontinue'])) {
                if ($subcat) {
                    foreach ($pages as $p) {
                        if (substr($p,0,9)=='Category:') {
                            $pages2 = $this->categorymembers($p,true);
                            $pages = array_merge($pages,$pages2);
                        }
                    }
                }
                return $pages;
            } else {
                $continue = '&rawcontinue=&cmcontinue='.urlencode($res['query-continue']['categorymembers']['cmcontinue']);
            }
        }
    }

    /**
     * Returns the number of pages in a category
     * @param $category The category to use (including prefix)
     * @return integer
     **/
    function categorypagecount ($category) {
        $res = $this->query('?action=query&format=json&titles='.urlencode($category).'&prop=categoryinfo&formatversion=2');
        return $res['query']['pages'][0]['categoryinfo']['pages'];
    }

    /**
     * Returns a list of pages that link to $page.
     * @param $page
     * @param $extra (defaults to null)
     * @param $ns (defaults to null)
     * @return array
     **/
    function whatlinkshere ($page,$extra=null,$ns=null) {
        $continue = '&rawcontinue=';
        $pages = array();
        while (true) {
            $res = $this->query('?action=query&list=backlinks&bltitle='.urlencode($page).'&blnamespace='.$ns.'&bllimit=500&format=json'.$continue.$extra);
            if (isset($res['error'])) {
                return false;
            }
            foreach ($res['query']['backlinks'] as $x) {
                $pages[] = $x['title'];
            }
            if (empty($res['query-continue']['backlinks']['blcontinue'])) {
                return $pages;
            } else {
                $continue = '&rawcontinue=&blcontinue='.urlencode($res['query-continue']['backlinks']['blcontinue']);
            }
        }
    }

    /**
    * Returns a list of pages that include the image.
    * @param $image
    * @param $extra (defaults to null)
    * @return array
    **/
    function whereisincluded ($image,$extra=null) {
        $continue = '&rawcontinue=';
        $pages = array();
        while (true) {
            $res = $this->query('?action=query&list=imageusage&iutitle='.urlencode($image).'&iulimit=500&format=json'.$continue.$extra);
            if (isset($res['error'])) {
                return false;
            }
            foreach ($res['query']['imageusage'] as $x) {
                $pages[] = $x['title'];
            }
            if (empty($res['query-continue']['imageusage']['iucontinue'])) {
                return $pages;
            } else {
                $continue = '&rawcontinue=&iucontinue='.urlencode($res['query-continue']['imageusage']['iucontinue']);
            }
        }
    }

    /**
    * Returns a list of pages that use the $template.
    * @param $template the template we are interested in
    * @param $extra (defaults to null)
    * @return array
    **/
    function whatusethetemplate ($template,$extra=null) {
        $continue = '&rawcontinue=';
        $pages = array();
        while (true) {
            $res = $this->query('?action=query&list=embeddedin&eititle=Template:'.urlencode($template).'&eilimit=500&format=json'.$continue.$extra);
            if (isset($res['error'])) {
                return false;
            }
            foreach ($res['query']['embeddedin'] as $x) {
                $pages[] = $x['title'];
            }
            if (empty($res['query-continue']['embeddedin']['eicontinue'])) {
                return $pages;
            } else {
                $continue = '&rawcontinue=&eicontinue='.urlencode($res['query-continue']['embeddedin']['eicontinue']);
            }
         }
     }

    /**
     * Returns an array with all the subpages of $page
     * @param $page
     * @return array
     **/
    function subpages ($page) {
        /* Calculate all the namespace codes */
        $ret = $this->query('?action=query&meta=siteinfo&siprop=namespaces&format=json');
        foreach ($ret['query']['namespaces'] as $x) {
            $namespaces[$x['*']] = $x['id'];
        }
        $temp = explode(':',$page,2);
        $namespace = $namespaces[$temp[0]];
        $title = $temp[1];
        $continue = '&rawcontinue=';
        $subpages = array();
        while (true) {
            $res = $this->query('?action=query&format=json&list=allpages&apprefix='.urlencode($title).'&aplimit=500&apnamespace='.$namespace.$continue);
            if (isset($x['error'])) {
                return false;
            }
            foreach ($res['query']['allpages'] as $p) {
                $subpages[] = $p['title'];
            }
            if (empty($res['query-continue']['allpages']['apfrom'])) {
                return $subpages;
            } else {
                $continue = '&rawcontinue=&apfrom='.urlencode($res['query-continue']['allpages']['apfrom']);
            }
        }
    }

    /**
     * This function takes a username and password and logs you into wikipedia.
     * @param $user Username to login as.
     * @param $pass Password that corrisponds to the username.
     * @return array
     **/
    function login ($user,$pass) {
        $post = array('lgname' => $user, 'lgpassword' => $pass);
        $ret = $this->query('?action=query&meta=tokens&type=login&format=json');
        #print_r($ret);
        /* This is now required - see https://bugzilla.wikimedia.org/show_bug.cgi?id=23076 */
        $post['lgtoken'] = $ret['query']['tokens']['logintoken'];
        $ret = $this->query( '?action=login&format=json', $post );

        if ($ret['login']['result'] != 'Success') {
            echo "Login error: \n";
            print_r($ret);
            die();
        } else {
            return $ret;
        }
    }

    /* crappy hack to allow users to use cookies from old sessions */
    function setLogin($data) {
        $this->http->cookie_jar = array(
        $data['cookieprefix'].'UserName' => $data['lgusername'],
        $data['cookieprefix'].'UserID' => $data['lguserid'],
        $data['cookieprefix'].'Token' => $data['lgtoken'],
        $data['cookieprefix'].'_session' => $data['sessionid'],
        );
    }

    /**
     * Check if we're allowed to edit $page.
     * See https://en.wikipedia.org/wiki/Template:Bots
     * for more info.
     * @param $page MediaWikiPage The page we want to edit.
     * @param $user string The bot's username.
     * @param $text string page text, will override page
     * @return bool true if we can edit
     **/
    function nobots ($page,$user=null,$text=null) {
        if ($text == null) {
            $text = $this->getpage($page);
        }
        if ($user != null) {
            if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.preg_quote($user,'/').'.*?)\}\}/iS',$text)) {
                return false;
            }
        } else {
            if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all)\}\}/iS',$text)) {
                return false;
            }
        }
        return true;
    }

    /**
     * This function returns the edit token for the current user.
     * @return string edit token.
     **/
    function getedittoken() {
        $x = $this->query('?action=query&meta=tokens&format=json');
        return $x['query']['tokens']['csrftoken'];
    }

    /**
     * Checks if $user has email enabled.
     * Uses index.php.
     * @param $user The user to check.
     * @return bool.
     **/
    function checkEmail($user) {
        $x = $this->query('?action=query&meta=allmessages&ammessages=noemailtext|notargettext&amlang=en&format=json');
        $messages[0] = $x['query']['allmessages'][0]['*'];
        $messages[1] = $x['query']['allmessages'][1]['*'];
        $page = $this->http->get(str_replace('api.php','index.php',$this->url).'?title=Special:EmailUser&target='.urlencode($user));
        if (preg_match('/('.preg_quote($messages[0],'/').'|'.preg_quote($messages[1],'/').')/i',$page)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns all the pages $page is transcluded on.
     * @param $page The page to get the transclusions from.
     * @param $sleep The time to sleep between requests (set to null to disable).
     * @return array.
     **/
    function getTransclusions($page,$sleep=null,$extra=null) {
        $continue = '&rawcontinue=';
        $pages = array();
        while (true) {
            $ret = $this->query('?action=query&list=embeddedin&eititle='.urlencode($page).$continue.$extra.'&eilimit=500&format=json');
            if ($sleep != null) {
                sleep($sleep);
            }
            foreach ($ret['query']['embeddedin'] as $x) {
                $pages[] = $x['title'];
            }
            if (isset($ret['query-continue']['embeddedin']['eicontinue'])) {
                $continue = '&rawcontinue=&eicontinue='.$ret['query-continue']['embeddedin']['eicontinue'];
            } else {
                return $pages;
            }
        }
    }

    /**
     * Returns all the talk pages (namespace=1) $page is transcluded on.
     * @param $page The page to get the transclusions from.
     * @param $sleep The time to sleep between requests (set to null to disable).
     * @return array.
     **/
    function getTalkTransclusions($page,$sleep=null,$extra=null) {
        $continue = '&rawcontinue=';
        $pages = array();
        while (true) {
            $ret = $this->query('?action=query&list=embeddedin&einamespace=1&eititle='.urlencode($page).$continue.$extra.'&eilimit=500&format=json');
            if ($sleep != null) {
                sleep($sleep);
            }
            foreach ($ret['query']['embeddedin'] as $x) {
                $pages[] = $x['title'];
            }
            if (isset($ret['query-continue']['embeddedin']['eicontinue'])) {
                $continue = '&rawcontinue=&eicontinue='.$ret['query-continue']['embeddedin']['eicontinue'];
            } else {
                return $pages;
            }
        }
    }

    /**
     * Returns the timestamp of the oldest deleted revision of $page.
     * @param $page
     * @return timestamp
     **/
    function getOldestDeletedRevisionTimestamp($page) {
        $res = $this->query('?action=query&format=json&prop=deletedrevisions&titles='.urlencode($page).'&drvprop=timestamp&drvlimit=1&drvdir=newer');
        if (isset($res['error'])) {
            return false;
        }
        foreach ($res['query']['pages'] as $x) {
            if (isset($x['deletedrevisions'][0]['timestamp'])) {
                return $x['deletedrevisions'][0]['timestamp'];
            }
        }
    }

    /**
     * Purges the cache of $page.
     * @param $page The page to purge.
     * @return array Api result.
     **/
    function purgeCache($page) {
        $params = array(
            'titles' => $page,
        );
        $ret = $this->query('?action=purge&format=json&formatversion=2&assert=user&forcerecursivelinkupdate=1',$params);
        if (!array_key_exists('purge', $ret)) {
            print_r($ret);
            return $ret;
        }
        foreach ($ret['purge'] as $x) {
            if (!array_key_exists('purged', $x) or $x['purged'] != true) {
                echo "\n? Not purged!\n";
                print_r($ret);
            }
            else if (!array_key_exists('linkupdate', $x) or $x['linkupdate'] != true) {
                echo "\n? No linkupdate!\n";
                print_r($x);
            }
        }
        return $ret;
    }

    /**
     * Edits a page.
     * @param $page Page name to edit.
     * @param $data Data to post to page.
     * @param $summary Edit summary to use.
     * @param $minor Whether or not to mark edit as minor.  (Default false)
     * @param $bot Whether or not to mark edit as a bot edit.  (Default true)
     * @return array api result
     **/
    function edit ($page,$data,$summary = '',$minor = false,$bot = true,$section = null,$detectEC=false,$maxlag='') {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'title' => $page,
            'text' => $data,
            'token' => $this->token,
            'summary' => $summary,
            ($minor?'minor':'notminor') => '1',
            ($bot?'bot':'notbot') => '1'
        );
        if ($section != null) {
            $params['section'] = $section;
        }
        if ($this->ecTimestamp != null && $detectEC == true) {
            $params['basetimestamp'] = $this->ecTimestamp;
            $this->ecTimestamp = null;
        }
        if ($maxlag!='') {
            $maxlag='&maxlag='.$maxlag;
        }
        return $this->query('?action=edit&format=json&assert=user'.$maxlag,$params);
    }

    /**
    * Add a text at the bottom of a page
    * @param $page The page we're working with.
    * @param $text The text that you want to add.
    * @param $summary Edit summary to use.
    * @param $minor Whether or not to mark edit as minor.  (Default false)
    * @param $bot Whether or not to mark edit as a bot edit.  (Default true)
    * @return array api result
    **/
    function addtext( $page, $text, $summary = '', $minor = false, $bot = true )
    {
        $data = $this->getpage( $page );
        $data.= "\n" . $text;
        return $this->edit( $page, $data, $summary, $minor, $bot );
    }

    /**
     * Moves a page.
     * @param $old Name of page to move.
     * @param $new New page title.
     * @param $reason Move summary to use.
     * @param $movetalk Move the page's talkpage as well.
     * @return array api result
     **/
    function move ($old,$new,$reason,$options=null) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'from' => $old,
            'to' => $new,
            'token' => $this->token,
            'reason' => $reason
        );
        if ($options != null) {
            $option = explode('|',$options);
            foreach ($option as $o) {
                $params[$o] = true;
            }
        }
        return $this->query('?action=move&format=json&assert=user',$params);
    }

    /**
     * Rollback an edit.
     * @param $title Title of page to rollback.
     * @param $user Username of last edit to the page to rollback.
     * @param $reason Edit summary to use for rollback.
     * @param $bot mark the rollback as bot.
     * @return array api result
     **/
    function rollback ($title,$user,$reason=null,$bot=false) {
        $ret = $this->query('?action=query&prop=revisions&rvtoken=rollback&titles='.urlencode($title).'&format=json');
        foreach ($ret['query']['pages'] as $x) {
            $token = $x['revisions'][0]['rollbacktoken'];
            break;
        }
        $params = array(
            'title' => $title,
            'user' => $user,
            'token' => $token
        );
        if ($bot) {
            $params['markbot'] = true;
        }
        if ($reason != null) {
            $params['summary'] = $reason;
        }
        return $this->query('?action=rollback&format=json&assert=user',$params);
    }

    /**
     * Blocks a user.
     * @param $user The user to block.
     * @param $reason The block reason.
     * @param $expiry The block expiry.
     * @param $options a piped string containing the block options.
     * @return array api result
     **/
    function block ($user,$reason='vand',$expiry='infinite',$options=null,$retry=true) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'expiry' => $expiry,
            'user' => $user,
            'reason' => $reason,
            'token' => $this->token
        );
        if ($options != null) {
            $option = explode('|',$options);
            foreach ($option as $o) {
                $params[$o] = true;
            }
        }
        $ret = $this->query('?action=block&format=json&assert=user',$params);
        /* Retry on a failed token. */
        if ($retry and $ret['error']['code']=='badtoken') {
            $this->token = $this->getedittoken();
            return $this->block($user,$reason,$expiry,$options,false);
        }
        return $ret;
    }

    /**
     * Unblocks a user.
     * @param $user The user to unblock.
     * @param $reason The unblock reason.
     * @return array api result
     **/
    function unblock ($user,$reason) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'user' => $user,
            'reason' => $reason,
            'token' => $this->token
        );
        return $this->query('?action=unblock&format=json&assert=user',$params);
    }

    /**
     * Emails a user.
     * @param $target The user to email.
     * @param $subject The email subject.
     * @param $text The body of the email.
     * @param $ccme Send a copy of the email to the user logged in.
     * @return array api result
     **/
    function email ($target,$subject,$text,$ccme=false) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'target' => $target,
            'subject' => $subject,
            'text' => $text,
            'token' => $this->token
        );
        if ($ccme) {
            $params['ccme'] = true;
        }
        return $this->query('?action=emailuser&format=json&assert=user',$params);
    }

    /**
     * Deletes a page.
     * @param $title The page to delete.
     * @param $reason The delete reason.
     * @return array api result
     **/
    function delete ($title,$reason) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'title' => $title,
            'reason' => $reason,
            'token' => $this->token
        );
        return $this->query('?action=delete&format=json&assert=user',$params);
    }

    /**
     * Undeletes a page.
     * @param $title The page to undelete.
     * @param $reason The undelete reason.
     * @return array api result
     **/
    function undelete ($title,$reason) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'title' => $title,
            'reason' => $reason,
            'token' => $this->token
        );
        return $this->query('?action=undelete&format=json&assert=user',$params);
    }

    /**
     * (Un)Protects a page.
     * @param $title The page to (un)protect.
     * @param $protections The protection levels (e.g. 'edit=autoconfirmed|move=sysop')
     * @param $expiry When the protection should expire (e.g. '1 day|infinite')
     * @param $reason The (un)protect reason.
     * @param $cascade Enable cascading protection? (defaults to false)
     * @return array api result
     **/
    function protect ($title,$protections,$expiry,$reason,$cascade=false) {
        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }
        $params = array(
            'title' => $title,
            'protections' => $protections,
            'expiry' => $expiry,
            'reason' => $reason,
            'token' => $this->token
        );
        if ($cascade) {
            $params['cascade'] = true;
        }
        return $this->query('?action=protect&format=json&assert=user',$params);
    }

    /**
     * Uploads an image.
     * @param $page The destination file name.
     * @param $file The local file path.
     * @param $desc The upload discrption (defaults to '').
     **/
     function upload ($page,$file,$desc='') {
        if ($this->token == null) {
                $this->token = $this->getedittoken();
        }
        $params = array(
            'filename'        => $page,
            'comment'         => $desc,
            'text'            => $desc,
            'token'           => $this->token,
            'ignorewarnings'  => '1',
            'file'            => new CURLFile(realpath($file))
        );
        return $this->query('?action=upload&format=json&assert=user',$params);
     }

    /**
     * @param $page - page
     * @param $revs - rev ids to delete (seperated with ,)
     * @param $comment - delete comment
     */
    function revdel ($page,$revs,$comment) {

        if ($this->token==null) {
            $this->token = $this->getedittoken();
        }

        $post = array(
            'wpEditToken'       => $this->token,
            'ids' => $revs,
            'target' => $page,
            'type' => 'revision',
            'wpHidePrimary' => 1,
            'wpHideComment' => 1,
            'wpHideUser' => 0,
            'wpRevDeleteReasonList' => 'other',
            'wpReason' => $comment,
            'wpSubmit' => 'Apply to selected revision(s)'
        );
        return $this->http->post(str_replace('api.php','index.php',$this->url).'?title=Special:RevisionDelete&action=submit',$post);
    }

    /**
     * Creates a new account.
     * Uses index.php as there is no api to create accounts yet :(
     * @param $username The username the new account will have.
     * @param $password The password the new account will have.
     * @param $email The email the new account will have.
     **/
    function createaccount ($username,$password,$email=null) {
        $post = array(
            'wpName' => $username,
            'wpPassword' => $password,
            'wpRetype' => $password,
            'wpEmail' => $email,
            'wpRemember' => 0,
            'wpIgnoreAntiSpoof' => 0,
            'wpCreateaccount' => 'Create account',
        );
        return $this->http->post(str_replace('api.php','index.php',$this->url).'?title=Special:UserLogin&action=submitlogin&type=signup',$post);
    }

    /**
     * Changes a users rights.
     * @param $user   The user we're working with.
     * @param $add    A pipe-separated list of groups you want to add.
     * @param $remove A pipe-separated list of groups you want to remove.
     * @param $reason The reason for the change (defaults to '').
     **/
    function userrights ($user,$add,$remove,$reason='') {
        // get the userrights token
        $token = $this->query('?action=query&list=users&ususers='.urlencode($user).'&ustoken=userrights&format=json');
        $token = $token['query']['users'][0]['userrightstoken'];
        $params = array(
            'user' => $user,
            'token' => $token,
            'add' => $add,
            'remove' => $remove,
            'reason' => $reason
        );
        return $this->query('?action=userrights&format=json&assert=user',$params);
    }

    /**
     * Gets the number of images matching a particular sha1 hash.
     * @param $hash The sha1 hash for an image.
     * @return int The number of images with the same sha1 hash.
     **/
    function imagematches ($hash) {
        $x = $this->query('?action=query&list=allimages&format=json&aisha1='.$hash);
        return count($x['query']['allimages']);
    }

    /**  BMcN 2012-09-16
     * Retrieve a media file's actual location.
     * @param $page The "File:" page on the wiki which the URL of is desired.
     * @return string|false The URL pointing directly to the media file (Eg http://upload.mediawiki.org/wikipedia/en/1/1/Example.jpg)
     **/
    function getfilelocation ($page) {
        $x = $this->query('?action=query&format=json&prop=imageinfo&titles='.urlencode($page).'&iilimit=1&iiprop=url');
        foreach ($x['query']['pages'] as $ret ) {
            if (isset($ret['imageinfo'][0]['url'])) {
                return $ret['imageinfo'][0]['url'];
            } else {
                return false;
            }
        }
    }

    /**  BMcN 2012-09-16
     * Retrieve a media file's uploader.
     * @param $page The "File:" page
     * @return string|false The user who uploaded the topmost version of the file.
     **/
    function getfileuploader ($page) {
        $x = $this->query('?action=query&format=json&prop=imageinfo&titles='.urlencode($page).'&iilimit=1&iiprop=user');
        foreach ($x['query']['pages'] as $ret ) {
            if (isset($ret['imageinfo'][0]['user'])) {
                return $ret['imageinfo'][0]['user'];
            } else {
                return false;
            }
        }
    }
}

/**
 * This class extends the wiki class to provide an high level API for the most commons actions.
 * @author Fale
 * @author Pavel Malahov
 **/
class extended extends wikipedia
{
    /**
     * Add a category to a page
     * @param $page The page we're working with.
     * @param $category The category that you want to add.
     * @param $summary Edit summary to use.
     * @param $minor Whether or not to mark edit as minor.  (Default false)
     * @param $bot Whether or not to mark edit as a bot edit.  (Default true)
     * @return array api result
     **/
    function addcategory( $page, $category, $summary = '', $minor = false, $bot = true )
    {
        $data = $this->getpage( $page );
        $data.= "\n[[Category:" . $category . "]]";
        return $this->edit( $page, $data, $summary, $minor, $bot );
    }

    /**
     * Find a string
     * @param $page The page we're working with.
     * @param $string The string that you want to find.
     * @return bool value (1 found and 0 not-found)
     **/
    function findstring( $page, $string )
    {
        $data = $this->getpage( $page );
        if( strstr( $data, $string ) ) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Replace a string
     * @param $page The page we're working with.
     * @param $string The string that you want to replace.
     * @param $newstring The string that will replace the present string.
     * @return string the new text of page
     **/
    function replacestring( $page, $string, $newstring )
    {
        $data = $this->getpage( $page );
        return str_replace( $string, $newstring, $data );
    }

    /**
     * Get a template from a page
     * @param $page The page we're working with
     * @param $template The name of the template we are looking for
     * @return string|null the searched (NULL if the template has not been found)
     **/
    function gettemplate( $page, $template ) {
        $data = $this->getpage( $page );
        $template = preg_quote( $template, " " );
        $r = "/{{" . $template . "(?:[^{}]*(?:{{[^}]*}})?)+(?:[^}]*}})?/i";
        preg_match_all( $r, $data, $matches );
        if( isset( $matches[0][0] ) ) {
            return $matches[0][0];
        } else {
            return NULL;
        }
    }

    /**
     * Get a list of all pages
     * @param $namespace Namespace to query (default=0)
     * @param $from Start list from the given page name
     * @param $to End list with the given page name
     * @return array with all pages if the following format:
			array (
			  0 => array (
				'pageid' => 1,
				'ns' => 0,
				'title' => 'Page 1 name',
			  ),
			  1 => array (
				'pageid' => 2,
				'ns' => 0,
				'title' => 'Page 2 name',
			  ),
			)
	 * @author Pavel Malahov
     * more info: http://www.mediawiki.org/wiki/API:Allpages
     */
    function allpages($from, $namespace = 0, $limit = 100) {
		$q = "?action=query&list=allpages&apfrom=$from&apnamespace=$namespace&aplimit=$limit&format=php";
		//wfDebug("WikiFarm. Bot allpages query  \n\tname: $wikiname  \n\tapi: $api  \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['allpages'];
		return $res;
    }

    /**
     * Get a list of recent changes
     * @param $namespace Namespace to query (default=0)
     * @return array with changes:
			array (
			  0 =>
			  array (
				'type' => 'new',
				'ns' => 0,
				'title' => 'Page 1 name',
				'rcid' => 24,
				'pageid' => 7,
				'revid' => 15,
				'old_revid' => 0,
				'user' => 'User name or IP',
		        'minor' => '',
				'anon' => '',
				'new' => '',
				'oldlen' => 0,
				'newlen' => 53,
		        'timestamp' => '2012-02-15T04:15:26Z',
		        'comment' => 'summary for the page',
			  ),
			 )
	 * @author Pavel Malahov
	 * more info: http://www.mediawiki.org/wiki/API:Recentchanges
	 */
    function recentchanges( $request, $user) {
		$prop = '&rcprop=timestamp|title|ids|sizes|flags|user|comment';
		$type = '&rctype=edit|new';

		$limit = $request->getInt('limit');			if (empty($limit))	{ $limit = 50; }
		$limit = "&rclimit=$limit";

		$namespace = $request->getInt('namespace');	if (empty($namespace))	{ $namespace = '0|8'; }
		$namespace = "&rcnamespace=$namespace";

		$hms = $request->getInt('hidemyself');
		if ($hms) {$hms = "&rcexcludeuser=". $user->getName();}

		$hide = '';
		$hide .= $request->getInt('hideminor') ? '!minor|' : '';
		$hide .= $request->getInt('hidebots') ? '!bot|' : '';
		$hide .= $request->getInt('hideanons') ? '!anon' : '';
		$hide_filter =  empty($hide) ? '' : "&rcshow=". $hide;

		$days = $request->getInt('days');			if (empty($days))	{ $days = 7; }
		$start = "&rcstart=". time();
		$end = "&rcend=" . mktime(0, 0, 0, date("m"), date("d") - $days, date("Y"));
		$sort_direction = "&rcdir=older";

		$q = "?action=query&list=recentchanges&format=php". $prop . $type . $limit . $namespace. $start . $end . $sort_direction . $hide_filter;
		$arr = $this->query($q);
		$res = $arr['query']['recentchanges'];
		return $res;
    }

    /**
     * Get a list of interwiki
     * @return array
     * @author Pavel Malahov
     */
    function interwikilist() {
		$q = "?action=query&meta=siteinfo&siprop=interwikimap&format=php";
		//wfDebug("WikiFarm. Interwiki list\n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['interwikimap'];
		return $res;
    }

    /**
     * Get a list of all users
     * @return array
     * @author Pavel Malahov
	 * more info: https://www.mediawiki.org/wiki/API:Allusers
     */
    function allusers() {
		$q = "?action=query&list=allusers&format=php&aulimit=5000&auprop=blockinfo|groups|editcount|registration";
		//wfDebug("WikiFarm. Bot all users \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['allusers'];
		return $res;
    }

    /**
     * Get a list of all non-empty categories
     * @return array
     * @author Pavel Malahov
	 * more info: https://www.mediawiki.org/wiki/API:Allcategories
     */
    function allcategories() {
		$q = "?action=query&list=allcategories&aclimit=5000&format=php";
		//wfDebug("WikiFarm. Bot all categories \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['allcategories'];
		return $res;
    }

    /**
     * Get a list of all templates
     * @return array
     * @author Pavel Malahov
	 * more info: https://www.mediawiki.org/wiki/API:Allpages
     */
    function alltemplates() {
		$q = "?action=query&list=allpages&apnamespace=10&aplimit=500&format=php";
		//wfDebug("\tWikiFarm. Bot all categories \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['allpages'];
		return $res;
    }

    /**
     * Get wiki general info
     * @return array
     * @author Pavel Malahov
	 * more info: https://www.mediawiki.org/wiki/API:Meta
     */
    function siteinfo() {
		$q = "?action=query&meta=siteinfo&format=php";
		//wfDebug("\tWikiFarm. Bot site info \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['general'];
		return $res;
    }

    /**
     * Get wiki statistics
     * @return array
     * @author Pavel Malahov
	 * more info: https://www.mediawiki.org/wiki/API:Meta
     */
    function sitestatistics() {
		$q = "?action=query&meta=siteinfo&format=php&siprop=statistics";
		//wfDebug("\tWikiFarm. Bot site statistics \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['statistics'];
		return $res;
    }
    
    /**
     * Get certain statistics value
     * @param $item Name of an item 
     * @param $value Parameter if needed
     * @return value for item
     * @author Pavel Malahov
     */
	function statvalue($item, $value='') {
		switch ($item) {
			case 'pages':		/* return number of ...*/
			case 'articles':
			case 'edits':
			case 'images':
			case 'users':
			case 'activeusers':
			case 'admins':
				$arr = $this->sitestatistics();
				$res = $arr[$item];				
				break;

			case 'category':	/* return number of articles in category */
				$arr = $this->categorymembers($value);
				$res = count($arr);
				break;
		}
		return $res;		
	}

    /**
     * Search for string
     * @param $str String to search
     * @return array with search result
     * @author Pavel Malahov
     * more info:	https://www.mediawiki.org/wiki/API:Search
	 *				https://www.mediawiki.org/wiki/API:Opensearch
     */
    function search($str, $what='title', $limit=10) {
		$q = "?action=query&list=search&format=php&srsearch=$str&srlimit=$limit&srwhat=$what";
		//wfDebug("\tWikiFarm. Bot search \n\tquery: $q\n");
		$arr = $this->query($q);
		$res = $arr['query']['search'];
		return $res;
    }
}