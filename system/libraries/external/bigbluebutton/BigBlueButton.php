<?php
class BigBlueButtonApi {

	protected $securitySalt;
	protected $bbbServerBaseUrl;

	public function __construct($securitySalt, $bbbServerBaseUrl) {
		$this->securitySalt     = $securitySalt;
		$this->bbbServerBaseUrl = $bbbServerBaseUrl;
	}

	public function createMeeting($params = array()) {
		$callName = 'create';
		$params   = ( is_array($params) ) ? $params : array();
		$checksum = hash('sha1', $callName . http_build_query($params) . $this->securitySalt);
		$params['checksum'] = $checksum;
		$url = $this->bbbServerBaseUrl . $callName . '?' . http_build_query($params);
		return $this->processXmlResponse($url);
	}

	public function joinMeeting($params = array()) {
		$callName = 'join';
		$params   = ( is_array($params) ) ? $params : array();
		$checksum = hash('sha1', $callName . http_build_query($params) . $this->securitySalt);
		$params['checksum'] = $checksum;
		return $url = $this->bbbServerBaseUrl . $callName . '?' . http_build_query($params);
	}

	public function isMeetingRunning($params = array()) {
		$callName = 'isMeetingRunning';
		$params   = ( is_array($params) ) ? $params : array();
		$checksum = hash('sha1', $callName . http_build_query($params) . $this->securitySalt);
		$params['checksum'] = $checksum;
		$url = $this->bbbServerBaseUrl . $callName . '?' . http_build_query($params);
		$xml = $this->processXmlResponse($url);
		return (isset($xml->running) && preg_match('/true/i', $xml->running)) ? true : false ;
	}

	public function endMeeting($params = array()) {
		$callName = 'end';
		$params   = ( is_array($params) ) ? $params : array();
		$checksum = hash('sha1', $callName . http_build_query($params) . $this->securitySalt);
		$params['checksum'] = $checksum;
		$url = $this->bbbServerBaseUrl . $callName . '?' . http_build_query($params);
		return $this->processXmlResponse($url);
	}

	public function getMeetingInfo($params = array()) {
		$callName = 'getMeetingInfo';
		$params   = ( is_array($params) ) ? $params : array();
		$checksum = hash('sha1', $callName . http_build_query($params) . $this->securitySalt);
		$params['checksum'] = $checksum;
		$url = $this->bbbServerBaseUrl . $callName . '?' . http_build_query($params);
		return $this->processXmlResponse($url);
	}

	private function processXmlResponse($url, $payload = '', $contentType = 'application/xml') {
		if (extension_loaded('curl')) {
			$ch = curl_init();
			if (!$ch) {
				throw new \RuntimeException('Unhandled curl error: ' . curl_error($ch));
			}
			$timeout = 10;
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			if ($payload != '') {
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Content-type: ' . $contentType,
					'Content-length: ' . strlen((string) $payload),
				]);
			}
			$data = curl_exec($ch);
			if ($data === false) {
				throw new \RuntimeException('Unhandled curl error: ' . curl_error($ch));
			}
			curl_close($ch);
			return new SimpleXMLElement($data);
		}
		if ($payload != '') {
			throw new \RuntimeException('Post XML data set but curl PHP module is not installed or not enabled.');
		}
		try {
			$response = simplexml_load_file($url, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
			return new SimpleXMLElement($response);
		} catch (\RuntimeException $e) {
			throw new \RuntimeException('Failover curl error: ' . $e->getMessage());
		}
	}
}
