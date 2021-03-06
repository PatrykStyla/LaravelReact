<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use PharData;
use ZipArchive;

class GetNewDD extends Command
{
    const enableCurlLogging = false;
    const base = "public/lolContent/";
    // TODO: Make locale dynamic to iterate over them.
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:DD';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */

    private string $lastVersion;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Copy remote file over HTTP one small chunk at a time.
     *
     * @param $infile The full URL to the remote file
     * @param $outfile The path where to save the file
     */
    public function copyfile_chunked($infile, $outfile)
    {
        $chunksize = 10 * (1024 * 1024); // 10 Megs

        /**
         * parse_url breaks a part a URL into it's parts, i.e. host, path,
         * query string, etc.
         */
        $parts = parse_url($infile);
        $i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
        $o_handle = fopen($outfile, 'wb');

        if ($i_handle == false || $o_handle == false) {
            return false;
        }

        if (!empty($parts['query'])) {
            $parts['path'] .= '?' . $parts['query'];
        }

        /**
         * Send the request to the server for the file
         */
        $request = "GET {$parts['path']} HTTP/1.1\r\n";
        $request .= "Host: {$parts['host']}\r\n";
        $request .= "User-Agent: Mozilla/5.0\r\n";
        $request .= "Keep-Alive: 115\r\n";
        $request .= "Connection: keep-alive\r\n\r\n";
        fwrite($i_handle, $request);

        /**
         * Now read the headers from the remote server. We'll need
         * to get the content length.
         */
        $headers = array();
        while (!feof($i_handle)) {
            $line = fgets($i_handle);
            if ($line == "\r\n") break;
            $headers[] = $line;
        }

        /**
         * Look for the Content-Length header, and get the size
         * of the remote file.
         */
        $length = 0;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Length:') === 0) {
                $length = (int) str_replace('Content-Length: ', '', $header);
                break;
            }
        }

        /**
         * Start reading in the remote file, and writing it to the
         * local file one chunk at a time.
         */
        $cnt = 0;
        while (!feof($i_handle)) {
            $buf = '';
            $buf = fread($i_handle, $chunksize);
            $bytes = fwrite($o_handle, $buf);
            if ($bytes == false) {
                return false;
            }
            $cnt += $bytes;

            /**
             * We're done reading when we've reached the conent length
             */
            if ($cnt >= $length) break;
        }

        fclose($i_handle);
        fclose($o_handle);
        return $cnt;
    }

    function curl(string $targetUrl, bool $assoc = false, array $additionalParameters = null)
    {

        $curl = curl_init();

        // curl set options
        curl_setopt_array($curl, [
            // 1(true) returns the body, 0(false) returns bool value
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $targetUrl,
            // retrive headers
            CURLOPT_HEADER => 1,
            CURLOPT_VERBOSE => self::enableCurlLogging,
        ]);
        //execute
        $response = curl_exec($curl);
        // curl connection info
        $info = curl_getinfo($curl);
        // Get header size from $info
        $curlHeaderSize = $info['header_size'];

        // Found on github https://gist.github.com/neopunisher/5292506. Converts string text to array
        $sBody = trim(mb_substr($response, $curlHeaderSize));
        $ResponseHeader = explode("\n", trim(mb_substr($response, 0, $curlHeaderSize)));

        // This removes the first entry. The response code
        unset($ResponseHeader[0]);
        $aHeaders = array();
        foreach ($ResponseHeader as $line) {
            list($key, $val) = explode(':', $line, 2);
            $aHeaders[strtolower($key)] = trim($val);
        }

        // add response code since it wasn't added by the above code.
        // Since the response code is not nicely formated from RIOT we will just add it from $info since we need it anyway
        $aHeaders["response_code"] = $info["http_code"];
        // Add the url for easier debugging
        // TODO: Remove?
        $aHeaders["targetUrl"] = $targetUrl;

        // TODO: Put checks in place if RIOT's API is slow/working
        if ($response === false) {
            // eh("something went wrong with curl request");
        }
        //close cURL
        curl_close($curl);
        // $assoc determined whether the array is converted to an object or an assosiative array
        $data = json_decode($sBody, $assoc);

        // check for response code and proceed accordingly

        if ($aHeaders["response_code"] == 200) {
            return $data;
        }
        // Check the reponse code error
        else {
            // TODO: Add stuff for different errors?
            switch ($aHeaders["response_code"]) {
                    // Bad request
                case 400:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Unauthorized
                case 401:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Forbidden (no API key/ wrong API key)
                case 403:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Data not found
                case 404:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Method not allowed
                case 405:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Unsupported media type
                case 415:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Limit reached
                case 429:
                    // There is a possiblity when an endpoint goes down to return this error
                    // In this case the headers wont have the retry-after property
                    if (isset($aHeaders["retry-after"])) {
                        die("Error limit reached" . " retry-after " . $aHeaders["retry-after"] . " seconds");
                    } else {
                    }

                    break;

                    // Errors from RIOT servers
                    // Internal server error
                case 500:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    //Bad gateway
                case 502:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Service unavailable
                case 503:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                    // Gateway timeout
                case 504:
                    // eh("error " . $aHeaders["response_code"] . " Code");
                    break;
                default:
                    throw new Exception("Unknown error " . $aHeaders["response_code"] . " Code");
                    break;
            }

            return null;
        }
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var string[] $versions */
        $this->LogToText("Started");
        // get ALL versions
        $response = $this->curl("https://ddragon.leagueoflegends.com/api/versions.json");
        // get the latest version
        $a = $response[0];
        $this->lastVersion = str_replace('"', "", $a);

        $this->InitLog(self::base . "Updatelog.txt");

        $fileName = "dragontail-" . $this->lastVersion . ".zip";

        // $fp = fopen('public/version.json', 'w');
        // $json = json_encode(['version' => "$lastVersion"]);
        // fwrite($fp, $json);
        // fclose($fp);

        $path = self::base . $fileName;
        if (file_exists(self::base . $this->lastVersion)) {
            $this->LogToText("Version is up to date");
            // We have the latest version do nothing
        } else {
            // Download the latest version
            // **** For some reason Riot can't decide on .zip or .tgz so both will be there until I can fidure to dynamically detect it ****
            // $this->copyfile_chunked("https://ddragon.leagueoflegends.com/cdn/dragontail-$lastVersion.zip", $path);
            $this->copyfile_chunked("https://ddragon.leagueoflegends.com/cdn/dragontail-$this->lastVersion.tgz", $path);
            $this->LogToText("File downloaded. Version: $this->lastVersion");
            // unzip

            $this->UnZip($this->lastVersion);
            $this->UnTar($this->lastVersion, $fileName);

            $this->ConvertJsons();
        }
    }

    public function UnZip(string $lastVersion)
    {
        try {
            $dest = self::base . $lastVersion;

            $zip = new ZipArchive;
            if ($zip->open(self::base . "dragontail-$lastVersion.zip") === TRUE) {
                $zip->extractTo($dest);
                $zip->close();

                $this->LogToText("Done");
            } else {
                $this->LogToText("Zip file not found");
            }
        } catch (Exception $e) {
            dump($e);
            $this->LogToText($e);
        }
    }

    public function UnTar(string $lastVersion, string $fileName)
    {
        // has .tgz
        $path = self::base . $fileName;
        try {
            $dest = self::base . "dragontail-$lastVersion.tar";
            // The .tgz cannot be extracted with PharData since it runs into memory issues
            // This function extract the xxx.tar which can be extracted with PharData
            $this->uncompress($path, $dest);
            $this->LogToText("First decompression sucesfull");
            // Remove xxx.tgz
            unlink($path);
            // Extract xxx.tar
            $phar = new PharData($dest);
            $phar->extractTo(self::base . $lastVersion);
            $this->LogToText("Second decompression sucesfull.");

            // Remove xxx.tar
            unlink($dest);
            $this->LogToText("Done");
        } catch (Exception $e) {
            dump($e);
            $this->LogToText($e);
        }
    }

    public function LogToText(string $TextLog)
    {
        $fp = fopen(self::base . "Updatelog.txt", 'a');
        fwrite($fp, date("Y-m-d H:i:s") . ": " . "$TextLog\n");
        fclose($fp);

        echo $TextLog . "\n";
    }

    public function InitLog(string $text)
    {
        if (!file_exists(self::base . "Updatelog.txt")) {
            // Log file does not exists create new
            $fp = fopen(self::base . "Updatelog.txt", 'w');
            fwrite($fp, date("Y-m-d H:i:s") . ": " . "File Created\n");
            fclose($fp);
        } else {
            $this->LogToText("File opened");
        }
    }
    public function uncompress($srcName, $dstName)
    {
        $sfp = gzopen($srcName, "rb");
        $fp = fopen($dstName, "w");

        while (!gzeof($sfp)) {
            $string = gzread($sfp, 4096);
            fwrite($fp, $string, strlen($string));
        }
        gzclose($sfp);
        fclose($fp);
    }

    public function ConvertJsons()
    {
        $this->ConvertChampions();
        $this->ConvertRunes();
        $this->ConvertSummonerSpells();
    }

    private function ConvertChampions()
    {
        /** @var array $data */
        $data = file_get_contents(self::base."$this->lastVersion/$this->lastVersion/data/en_GB/" . "champion.json");
        $data = json_decode($data, true);
        // Convert names => IDs
		$data_by_key = $data;
		$data_by_key['data'] = [];

		array_walk($data['data'], function( $d ) use (&$data_by_key) {
			$data_by_key['data'][(int)$d['key']] = $d;
		});
        
        // Add an array with IDs => names
		$data_by_key['keys'] = array_map(function ($d) {
			return $d['id'];
		}, $data_by_key['data']);
		$data_by_key['keys'] = array_flip($data_by_key['keys']);

        file_put_contents(self::base."$this->lastVersion/$this->lastVersion/data/en_GB/" . "championByKey.json", json_encode($data_by_key));

        $this->LogToText("Converted Champions");
    }

    private function ConvertSummonerSpells()
    {
        /** @var array $data */
        $data = file_get_contents(self::base."$this->lastVersion/$this->lastVersion/data/en_GB/" . "summoner.json");
        $data = json_decode($data, true);

		$data_by_key = $data;
		$data_by_key['data'] = [];

		array_walk($data['data'], function( $d ) use (&$data_by_key) {
			$data_by_key['data'][(int)$d['key']] = $d;
		});

        file_put_contents(self::base."$this->lastVersion/$this->lastVersion/data/en_GB/" . "summonerByKey.json", json_encode($data_by_key));

        $this->LogToText("Converted Spells");
    }
    private function ConvertRunes()
    {
        /** @var array $data */
        $data = file_get_contents(self::base."$this->lastVersion/$this->lastVersion/data/en_GB/" . "runesReforged.json");
        $data = json_decode($data, true);

        $r = [];
		foreach ($data as $path) {
			$r[$path['id']] = [
				'key' => $path['key'], 
				'icon' => $path['icon'], 
				'name' => $path['name'] 
				];

			foreach ($path['slots'] as $slot) {
				foreach ($slot['runes'] as $item) {
					$r[$item['id']] = $item;
				}
			}
        }
        
        file_put_contents(self::base."$this->lastVersion/$this->lastVersion/data/en_GB/" . "runesReforgedByKey.json", json_encode($r));

        $this->LogToText("Converted Runes");
    }
}