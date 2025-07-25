<?php

namespace App;

use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\JsonResource;

trait HasJsonResponse
{
    /**
     * @var int
     */
    protected int $RESPONSE_SUCCESS = 200;

    /**
     * @var int
     */
    protected int $RESPONSE_CREATED = 201;

    /**
     * @var int
     */
    protected int $RESPONSE_NO_CONTENT = 204;

    /**
     * @var int
     */
    protected int $RESPONSE_BAD_REQUEST = 400;

    /**
     * @var int
     */
    protected int $RESPONSE_UNAUTHORIZED = 401;

    /**
     * @var int
     */
    protected int $RESPONSE_FORBIDDEN = 403;

    /**
     * @var int
     */
    protected int $RESPONSE_NOT_FOUND = 404;

    /**
     * @var int
     */
    protected int $RESPONSE_UNPROCESSABLE = 422;

    /**
     * @var int
     */
    protected int $RESPONSE_SERVER_ERROR = 500;

    /**
     * @var int
     */
    protected int $RESPONSE_BAD_GATEWAY = 502;

    /**
     * @var int
     */
    protected int $RESPONSE_SERVICE_UNAVAILABLE = 503;

    /**
     * @var int
     */
    protected int $RESPONSE_GATEWAY_TIMEOUT = 504;

    /**
     * @param array $data
     * @param int $code
     * @return Response
     */
    public function json(array $data, int $code = 200): Response
    {
        return response($data, $code);
    }

    /**
     * @param JsonResource $resource
     * @return Response
     */
    public function jsonResource(JsonResource $resource): Response
    {
        return response($resource, $this->RESPONSE_SUCCESS);
    }

    /**
     * @param string $message
     * @param array $data Additional data to include in the response
     * @return Response
     */
    public function jsonSuccess(string $message = 'Operation performed successfully', array $data = []): Response
    {
        $response = ['success' => true, 'message' => $message];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        return response($response, $this->RESPONSE_SUCCESS);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonNotFound(string $message = 'Resource not found'): Response
    {
        return response(['error' => $message], $this->RESPONSE_NOT_FOUND);
    }



    /**
     * @param string $message
     * @return Response
     */
    public function jsonServerError(string $message = 'Internal server error'): Response
    {
        return response(['message' => $message], $this->RESPONSE_SERVER_ERROR);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonUnAuthorized(string $message = 'Unauthorized'): Response
    {
        return response(['message' => $message], $this->RESPONSE_UNAUTHORIZED);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonBadRequest(string $message = 'Bad Request'): Response
    {
        return response(['message' => $message], $this->RESPONSE_BAD_REQUEST);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonCreated(string $message = 'Resource created successfully'): Response
    {
        return response(['message' => $message], $this->RESPONSE_CREATED);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonForbidden(string $message = 'Forbidden'): Response
    {
        return response(['message' => $message], $this->RESPONSE_FORBIDDEN);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonUnprocessable(string $message = 'Unprocessable Entity'): Response
    {
        return response(['message' => $message], $this->RESPONSE_UNPROCESSABLE);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonBadGateway(string $message = 'Bad Gateway'): Response
    {
        return response(['message' => $message], $this->RESPONSE_BAD_GATEWAY);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonServiceUnavailable(string $message = 'Service Unavailable'): Response
    {
        return response(['message' => $message], $this->RESPONSE_SERVICE_UNAVAILABLE);
    }

    /**
     * @param string $message
     * @return Response
     */
    public function jsonGatewayTimeout(string $message = 'Gateway Timeout'): Response
    {
        return response(['message' => $message], $this->RESPONSE_GATEWAY_TIMEOUT);
    }

    /**
     * @return Response
     */
    public function jsonNoContent(): Response
    {
        return response(null, $this->RESPONSE_NO_CONTENT);
    }
}