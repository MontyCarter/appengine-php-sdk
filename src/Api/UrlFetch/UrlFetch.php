<?php
declare(strict_types=1);

/**
 * Copyright 2021 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\AppEngine\Api\UrlFetch;

use Google\Appengine\Runtime\ApiProxy;
use Google\Appengine\Runtime\ApplicationError;
use google\appengine\URLFetchRequest;
use google\appengine\URLFetchRequest\RequestMethod;
use google\appengine\URLFetchResponse;
use google\appengine\URLFetchServiceError\ErrorCode;
use Exception;

final class UrlFetch
{

    /**
     * Maps UrlFetch error codes.
     *
     * @param ApplicationError: UrlFetch application error.
     *
     * @throws \Exception with error information.
     */
    private static function errorCodeToException(int $error): Exception
    {
        $errorCodeMap = [
            ErrorCode::OK => 'Module Return OK',
            ErrorCode::INVALID_URL => 'Invalid URL',
            ErrorCode::FETCH_ERROR => 'Fetch Error',
            ErrorCode::UNSPECIFIED_ERROR => 'Unexpected Error',
            ErrorCode::RESPONSE_TOO_LARGE => 'Response Too Large',
            ErrorCode::DEADLINE_EXCEEDED => 'Deadline Exceeded',
            ErrorCode::SSL_CERTIFICATE_ERROR => 'Ssl Certificate Error',
            ErrorCode::DNS_ERROR => 'Dns Error',
            ErrorCode::CLOSED => 'Closed Error',
            ErrorCode::INTERNAL_TRANSIENT_ERROR => 'Internal Transient Error',
            ErrorCode::TOO_MANY_REDIRECTS => 'Too Many Redirects',
            ErrorCode::MALFORMED_REPLY => 'Malformed Reply',
            ErrorCode::CONNECTION_ERROR => 'Connection Error',
            ErrorCode::PAYLOAD_TOO_LARGE => 'Payload Too Large',
        ];

        $errorCode = $errorCodeMap[$error->getApplicationError()] ?? (string) $error;

        return new Exception(sprintf(
            'UrlFetch Exception with Error Code: %s with message: %s',
            $errorCode,
            $error->getMessage()
        ) . PHP_EOL);
    }

    /**
     * Maps Request method string to URLFetch Request type.
     *
     * @param string $request_method: Specifies the HTTP request type.
     *
     * @throws \Exception for invalid $request_method input strings.
     *
     * @return URLFetchRequest\RequestMethod type.
     */
    private function getRequestMethod(string $request_method): int
    {
        switch ($request_method) {
            case 'GET':
                return RequestMethod::GET;
            case 'POST':
                return RequestMethod::POST;
            case 'HEAD':
                return RequestMethod::HEAD;
            case 'PUT':
                return RequestMethod::PUT;
            case 'DELETE':
                return RequestMethod::DELETE;
            case 'PATCH':
                return RequestMethod::PATCH;
            default:
                throw new Exception('Invalid Request Method Input: ' . $request_method);
        }
    }

    /**
     * Fetches a URL.
     *
     * @param string $url: Specifies the URL.
     * @param string $request_method: Optional, The HTTP method.
     *     URLs are fetched using one of the following HTTP methods:
     *     - GET
     *     - POST
     *     - HEAD
     *     - PUT
     *     - DELETE
     *     - PATCH
     * @param array $headers: Optional, array containing values in the format of {key => value} pairs.
     * @param string $payload: Optional, payload for a URL Request when using POST, PUT, and PATCH requests.
     * @param bool $allow_truncated: Optional, specify if content is truncated.
     * @param bool $follow_redirects: Optional, specify if redirects are followed.
     * @param int $deadline: Optional, the timeout for the request in seconds.
     * @param bool $validate_certificate: Optional, If set to `true`, requests are not
     *     sent to the server unless the certificate is valid and signed by a trusted CA.
     *
     * @throws \Exception If UrlFetchRequest has an application failure, if illegal web protocol used,
     *     or if content is illegally truncated.
     *
     * @return URLFetchResponse Returns URLFetchResponse object upon success, else throws application error.
     *
     */
    public function fetch(
        string $url,
        string $request_method = 'GET',
        array $headers = [],
        string $payload = '',
        bool $allow_truncated = true,
        bool $follow_redirects = true,
        float $deadline = 0.0,
        bool $validate_certificate = false
    ): URLFetchResponse {
        if (strncmp($url,'http://', 7) != 0 && strncmp($url,'https://', 8)!= 0) {
            throw new Exception('URL input must use http:// or https://');
        }

        // Only allow validate certificate for https requests.
        if (strncmp($url,'http://', 7) == 0) {
          $validate_certificate = false;
        }
    
        $req = new URLFetchRequest();
        $resp = new URLFetchResponse();

        // Request Method and URL.
        $req->setUrl($url);
        $req_method = $this->getRequestMethod($request_method);
        $req->setMethod($req_method);
    
        // Headers.
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $header = $req->addHeader();
                $header->setKey($key);
                $header->setValue($value);
            }
        }

        // Payload.
        if ($payload != '' && ($req_method == RequestMethod::POST || $req_method == RequestMethod::PUT 
                || $req_method == RequestMethod::PATCH)) {
            $req->setPayload($payload);
        }

        // Deadline.
        if ($deadline  > 0.0) {
            $req->setDeadline($deadline);
        }
    
        $req->setFollowredirects($follow_redirects);
        $req->setMustvalidateservercertificate($validate_certificate);

        try {
            ApiProxy::makeSyncCall(
                'urlfetch', 'Fetch', $req, $resp);
        } catch (ApplicationError $e) {
            throw self::errorCodeToException($e);
        }

        // Allow Truncated.
        if ($resp->getContentwastruncated() == true && !$allow_truncated) {
            throw new Exception('Output was truncated and allow_truncated option is not enabled.');
        }

        return $resp;
    }
}