<?php

namespace Ufz\ApiBase\Tests\Service\Geo;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Ufz\ApiBase\Service\Geo\GeoApiService;

class GeoApiServiceTest extends TestCase
{
    private const string ENDPOINT = 'geo_endpoint';

    /**
     * @var MockObject|HttpClientInterface
     */
    private $http;

    private GeoApiService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = self::createMock(HttpClientInterface::class);
        $this->geo = new GeoApiService(self::ENDPOINT, $this->http);
    }

    public function testRequestGraphQl()
    {
        $query = 'query';
        $json = '{"foo": "bar"}';

        $response = self::createMock(ResponseInterface::class);
        $this->http->expects(self::once())->method('request')->willReturn($response);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn($json);

        $result = $this->geo->requestGraphQl($query, "");
        self::assertEquals(json_decode($json, true), $result);
    }

    public function testRequestGraphQlFails()
    {
        $query = 'query';
        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_FORBIDDEN);
        $response->expects(self::never())->method('getContent');
        $this->http->expects(self::once())->method('request')->willReturn($response);

        self::expectException(TransportException::class);
        $this->geo->requestGraphQl($query, "");
    }

    public function testResolveLocationCoordinates()
    {
        $httpResponse = '{
           "data": {
             "lines": {
               "edges": [
                 {
                   "node": {
                     "_id": 10,
                     "geomJson": "{\"type\":\"MultiLineString\",\"coordinates\":[[[10,20]]]}",
                     "location": {
                        "_id": 1
                     }
                   }
                 },
                 {
                   "node": {
                     "_id": 20,
                     "geomJson": "{\"type\":\"MultiLineString\",\"coordinates\":[[[15,25], [50, 60]]]}",
                     "location": {
                        "_id": 2
                     }
                   }
                 }
               ]
             }
           }
         }';

        $id1 = 1;
        $id2 = 2;
        $id1String = '"' . $id1 . '"';
        $id2String = '"' . $id2 . '"';
        $httpResponse = sprintf($httpResponse);

        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn($httpResponse);
        $this->http->expects(self::once())->method('request')->willReturn($response);

        $array = $this->geo->resolveLocationCoordinates([$id1String, $id2String], "");

        $expectedArray = [
            $id1 => [10, 20],
            $id2 => [15, 25],
        ];

        self::assertEquals($expectedArray, $array);
    }

    public function testResolveLocationCoordinatesEmpty()
    {
        $array = $this->geo->resolveLocationCoordinates([], "");
        self::assertEmpty($array);
    }

    public function testResolveLocationCoordinatesUnsuccessful()
    {
        self::expectException(TransportException::class);
        $this->geo->resolveLocationCoordinates(['error'], "");
    }

    public function testResolveLocationCoordinatesWithInvalidResponseFormat()
    {
        $httpResponse = '{"foo":"bar"}';
        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn($httpResponse);
        $this->http->expects(self::once())->method('request')->willReturn($response);
        self::expectException(TransportException::class);
        self::expectExceptionMessage(sprintf("Geo Api Response is in unexpected format: %s.", $httpResponse));
        $this->geo->resolveLocationCoordinates(["1", "2", "3"], "");
    }

    public function testResolveLocationCoordinatesWith300Items()
    {
        $arr = [];
        $responseString = '{
           "data": {
             "lines": {
               "edges": []
             }
           }
         }';
        $responseJsons = [];
        // fill arr with ids as string, fill responseJsons with three sets of response jsons
        for ($i = 0; $i < 250; $i++) {
            $j = intdiv($i, 100);
            $arr[$i] = '"' . $i . '"';
            if (!isset($responseJsons[$j])) {
                $responseJsons[$j] = json_decode($responseString, true);
            }
            $responseJsons[$j]['data']['lines']['edges'][] = json_decode(
                sprintf('{"node": { "_id": %d, "geomJson": "{\"coordinates\":[[[0,0]]]}", "location": { "_id": %d } } }', $i + 1, $i),
                true
            );
        }
        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn(json_encode($responseJsons[0]), json_encode($responseJsons[1]), json_encode($responseJsons[2]));
        $this->http->expects(self::exactly(3))->method('request')->willReturn($response);
        $this->geo->resolveLocationCoordinates($arr, "");
    }

    public function testResolveLocationCoordinatesWithInvalidGeomJson()
    {
        $httpResponse = '{
           "data": {
             "lines": {
               "edges": [
                 {
                   "node": {
                     "_id": 0,
                     "geomJson": null,
                     "location": {
                        "_id": 1
                     }
                   }
                 }
               ]
             }
           }
         }';
        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn($httpResponse);
        $this->http->expects(self::once())->method('request')->willReturn($response);
        self::expectException(TransportException::class);
        self::expectExceptionMessage("Geo Api Response is invalid: GeomJson for location with id '1' is null.");
        $this->geo->resolveLocationCoordinates(["1"], "");
    }

    public function testResolveLocationCoordinatesWithInvalidLocation()
    {
        $httpResponse = '{
           "data": {
             "lines": {
               "edges": [
                 {
                   "node": {
                     "_id": 0,
                     "geomJson": "{\"type\":\"MultiLineString\",\"coordinates\":[[[10,20]]]}",
                     "location": null
                   }
                 }
               ]
             }
           }
         }';
        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn($httpResponse);
        $this->http->expects(self::once())->method('request')->willReturn($response);
        self::expectException(TransportException::class);
        self::expectExceptionMessage("Geo Api Response is invalid: Location id for line/point with id '0' is null.");
        $this->geo->resolveLocationCoordinates(["1"], "");
    }


    public function testResolveLocationCoordinatesWithNotEnoughResultValues()
    {
        $httpResponse = '{
           "data": {
             "lines": {
               "edges": [
                 {
                   "node": {
                     "_id": 10,
                     "geomJson": "{\"coordinates\":[[[0,0]]]}",
                     "location": {
                        "_id": 1
                     }
                   }
                 },
                 {
                   "node": {
                     "_id": 20,
                     "geomJson": "{\"coordinates\":[[[0,0]]]}",
                     "location": {
                        "_id": 2
                     }
                   }
                 }
               ]
             }
           }
         }';
        $response = self::createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn($httpResponse);
        $this->http->expects(self::once())->method('request')->willReturn($response);
        self::expectException(TransportException::class);
        self::expectExceptionMessage("Geo Api Response did not contain enough values. Requested location ids: [1,2,3]. Ids in response: [1,2]");
        $this->geo->resolveLocationCoordinates(["1", "2", "3"], "");
    }

    // mqu new Unit Tests
    //case 1: user input is less than 2 points
    public function testCreateGeoLocationFromGpsPointsWithNotEnoughValues()
    {
      $this->http->expects(self::never())->method("request");
      self::expectException(\InvalidArgumentException::class);
      self::expectExceptionMessage("At least 2 GPS tracking points are required");
      $this->geo->createGeoLocationFromGpsPoints([
        ["lat" => 51.307168, "lng" => 12.2478963],
      ], "");
    }

    //case 2: user input is two times the same point
    public function testCreateGeoLocationFromGpsPointsWith2RepeatedValues()
    {
      $this->http->expects(self::never())->method("request");
      self::expectException(\InvalidArgumentException::class);
      self::expectExceptionMessage("At least 2 different GPS tracking points are required");
      $this->geo->createGeoLocationFromGpsPoints([
        ["lat" => 51.307168, "lng" => 12.2478963],
        ["lat" => 51.307168, "lng" => 12.2478963],
      ], "");
    }

    //case 3: lat or lng out-of-range -> I added DataProvider to test different combinations
    /**
    * @dataProvider outOfRangeGpsPointsProvider
    */
    public function testCreateGeoLocationFromGpsPointsOutOfRange(array $gpsTrackingPoints)
    {
        $this->http->expects(self::never())->method("request");
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage("Provided tracking point is out of range");
        $this->geo->createGeoLocationFromGpsPoints($gpsTrackingPoints, "");
    }

    public static function outOfRangeGpsPointsProvider(): array
    {
        return [
            'latitude above 90' => [
                [
                    ["lat" => 251.307168, "lng" => 12.2478963],
                    ["lat" => 51.307168, "lng" => 12.2478963],
                ],
            ],
            'latitude below -90' => [
                [
                    ["lat" => -91, "lng" => 12.2478963],
                    ["lat" => 51.307168, "lng" => 12.2478963],
                ],
            ],
            'longitude above 180' => [
                [
                    ["lat" => 51.307168, "lng" => 181],
                    ["lat" => 51.307168, "lng" => 12.2478963],
                ],
            ],
            'longitude below -180' => [
                [
                    ["lat" => 51.307168, "lng" => -181],
                    ["lat" => 51.307168, "lng" => 12.2478963],
                ],
            ],
        ];
    }

    //case 4: user input is NaN
    public function testCreateGeoLocationFromGpsPointsNaN()
    {
      $this->http->expects(self::never())->method("request");
      self::expectException(\InvalidArgumentException::class);
      self::expectExceptionMessage("Numerical tracking points are required");
      $this->geo->createGeoLocationFromGpsPoints([
        ["lat" => "invalid", "lng" => 12.2478963],
        ["lat" => 51.307168, "lng" => 12.2478963],
      ], "");
    }
    




}
