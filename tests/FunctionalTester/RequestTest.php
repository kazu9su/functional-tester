<?php

namespace tests\FunctionalTester;

use FunctionalTester\tests\lib\TestCase;
use FunctionalTester\Request;

class RequestTest extends TestCase
{
    public function test_getPhpBin()
    {
        Request::addIncludePath('path/to/hoge');
        Request::addIniSet('hoge=1');

        $res = Request::getPhpBin();

        $this->assertRegExp(
            '/include_path="path\/to\/hoge\:/',
            $res
        );
        $this->assertRegExp(
            '/-d automatically_populate_raw_post_data=-1/',
            $res
        );
        $this->assertRegExp(
            '/-d hoge=1/',
            $res
        );
    }

    public function test_parseFilePath_without_query()
    {
        list($f, $q) = Request::parseFilePath('hoge.php');

        $this->assertEquals('hoge.php', $f);
        $this->assertEquals('', $q);
    }

    public function test_parseFilePath_with_query()
    {
        list($f, $q) = Request::parseFilePath('hoge.php?hoge=fuga&fuga=hoge');

        $this->assertEquals('hoge.php', $f);
        $this->assertEquals(
            'hoge=fuga&fuga=hoge',
            $q
        );
    }

    public function test_parseFilePath_with_query_and_empersand()
    {
        list($f, $q) = Request::parseFilePath('hoge.php?hoge=fuga&fuga=ho+ge#foobar');

        $this->assertEquals('hoge.php', $f);
        $this->assertEquals(
            'hoge=fuga&fuga=ho+ge',
            $q
        );
    }

    public function test_buildMultipartBody()
    {
        $body = Request::buildMultipartBody(
            ['hoge' => 'hoge hoge',
                'fuga' => 'fuga fuga',
            ],
            ['file1' => [
                    'name' => 'hoge.txt',
                    'type' => 'text/plain',
                    'content' => 'AABBCC',
                ],
                'file2' => 'tests/apps/file.txt',
            ]
        );

        $this->assertEquals(<<<END
--xYzZY
Content-Disposition: form-data; name="hoge"

hoge hoge
--xYzZY
Content-Disposition: form-data; name="fuga"

fuga fuga
--xYzZY
Content-Disposition: form-data; name="file1"; filename="hoge.txt"
Content-Type: text/plain

AABBCC
--xYzZY
Content-Disposition: form-data; name="file2"; filename="file.txt"
Content-Type: text/plain

AAAAAAAA
BBBBBB
CCCC
DD

--xYzZY--
END
            , $body
        );
    }

    public function test_simple_makeFakeRequest_succeeds()
    {
        list($ret, $stdout, $stderr) = Request::makeFakeRequest(
            ['REQUEST_METHOD' => 'POST',
                'SCRIPT_FILENAME' => 'tests/apps/respond.php',
                'QUERY_STRING' => 'aaa=aaa&bbb=bbb',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            'hello=world&bye=world'
        );

        $this->assertEquals(0, $ret);

        preg_match('/\r\n\r\n(.+)$/', $stdout, $matches);
        $data = json_decode($matches[1], true);

        $this->assertEquals([
            'GET' => [
                'aaa' => 'aaa',
                'bbb' => 'bbb',
            ],
            'POST' => [
                'hello' => 'world',
                'bye' => 'world',
            ],
            'FILES' => [],
        ], $data);
        $this->assertEquals('', $stderr);
    }

    public function test_complex_makeFakeRequest_succeeds()
    {
        list($ret, $stdout, $stderr) = Request::makeFakeRequest(
            ['REQUEST_METHOD' => 'POST',
                'SCRIPT_FILENAME' => 'tests/apps/respond.php',
                'QUERY_STRING' => 'aaa=aaa&bbb=bbb',
                'CONTENT_TYPE' => 'multipart/form-data; boundary=xYzZY',
            ],
            <<<END
--xYzZY
Content-Disposition: form-data; name="fuga"

fuga fuga
--xYzZY
Content-Disposition: form-data; name="hoge"

hoge hoge
--xYzZY
Content-Disposition: form-data; name="text_file"; filename="file.txt"
Content-Type: text/plain

abcdefg
--xYzZY--
END
        );

        $this->assertEquals(0, $ret);

        preg_match('/\r\n\r\n(.+)$/', $stdout, $matches);
        $data = json_decode($matches[1], true);

        $text_file_tmp_name = $data['FILES']['text_file']['tmp_name'];
        unset($data['FILES']['text_file']['tmp_name']);

        $this->assertEquals([
            'GET' => [
                'aaa' => 'aaa',
                'bbb' => 'bbb',
            ],
            'POST' => [
                'hoge' => 'hoge hoge',
                'fuga' => 'fuga fuga',
            ],
            'FILES' => [
                'text_file' => [
                    'name' => 'file.txt',
                    'type' => 'text/plain',
                    'size' => 7,
                    'error' => 0,
                ],
            ],
        ], $data);

        $this->assertRegExp('/\/tmp\//', $text_file_tmp_name);

        $this->assertEquals('', $stderr);
    }

    public function test_makeFakeRequest_fails()
    {
        list($ret, $stdout, $stderr) = Request::makeFakeRequest(
            ['REQUEST_METHOD' => 'GET',
                'SCRIPT_FILENAME' => 'tests/apps/die.php',
                'QUERY_STRING' => '',
            ],
            'hello=world&bye=world'
        );

        $this->assertEquals(255, $ret);
        $this->assertTrue(strlen($stderr) > 0);

        list($header, $body) = explode("\r\n\r\n", $stdout);

        $this->assertRegExp('/\Ahoge/', $body);
    }

    public function test_makeFakeResponse()
    {
        $response = Request::makeFakeResponse(
            "Status: 302 Moved Temporarily\r\nX-Hoge: hoge\r\n\r\n"
        );
        $this->assertEquals(
            "HTTP/1.1 302 Moved Temporarily\r\nStatus: 302 Moved Temporarily\r\nX-Hoge: hoge\r\n\r\n",
            $response
        );

        $response = Request::makeFakeResponse(
            "X-Hoge: hoge\r\n\r\nStatus: 403 Forbidden\r\n"
        );
        $this->assertEquals(
            "HTTP/1.1 200 OK\r\nX-Hoge: hoge\r\n\r\nStatus: 403 Forbidden\r\n",
            $response
        );
    }

    public function test_instance()
    {
        $req = new Request('GET', 'hoge.php');

        $this->assertInstanceOf("FunctionalTester\Request", $req);
        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals('hoge.php', $req->getFilePath());
        $this->assertEquals('', $req->getQueryString());
        $this->assertEquals([], $req->getContent());
        $this->assertEquals([], $req->getHeaders());
        $this->assertEquals([], $req->getFiles());
    }

    public function test_query_component_parsed()
    {
        $req = new Request('GET', 'hoge.php?hoge=fuga&foo=bar#hogefuga');

        $this->assertEquals('hoge.php', $req->getFilePath());
        $this->assertEquals(
            'hoge=fuga&foo=bar',
            $req->getQueryString()
        );
        $this->assertEquals([], $req->getContent());
    }

    public function test_arbitrary_header_is_normalized()
    {
        $req = new Request('GET', 'hoge.php', [], ['x-hoge' => 'foobar']);

        $this->assertEquals(
            ['HTTP_X_HOGE' => 'foobar'],
            $req->getHeaders()
        );
    }

    public function test_cgi_header_is_normalized()
    {
        $req = new Request('GET', 'hoge.php', [], ['content-type' => 'application/json']);

        $this->assertEquals(
            ['CONTENT_TYPE' => 'application/json'],
            $req->getHeaders()
        );
    }

    public function test_header_is_form()
    {
        $req = new Request('POST', 'hoge.php', ['hoge' => 'fuga']);

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals(
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $req->getHeaders()
        );
        $this->assertEquals(
            ['hoge' => 'fuga'],
            $req->getContent()
        );
    }

    public function test_query_and_form()
    {
        $req = new Request('POST', 'hoge.php?foo=bar', ['hoge' => 'fuga']);

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals(
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $req->getHeaders()
        );
        $this->assertEquals(
            'foo=bar',
            $req->getQueryString()
        );
        $this->assertEquals(
            'hoge=fuga',
            $req->getBody()
        );
    }

    public function test_artitrary_content()
    {
        $req = new Request(
            'POST',
            'hoge.php',
            '{"hoge":"fuga"}',
            ['content-type' => 'application/json']
        );

        $this->assertEquals('POST', $req->getMethod());
        $this->assertEquals(
            ['CONTENT_TYPE' => 'application/json'],
            $req->getHeaders()
        );
        $this->assertEquals(
            '{"hoge":"fuga"}',
            $req->getBody()
        );
    }

    public function test_send_for_code_200()
    {
        $req = new Request(
            'POST',
            'tests/apps/respond.php?a=a&b=b&c=c',
            ['hoge' => 'hoge',
                'fuga' => 'fuga',
            ],
            ['X-HOGE-FUGA' => 'hogehoge-fugafuga',
            ],
            ['file1' => ['name' => 'file1.txt', 'type' => 'text/plain', 'content' => 'あああ'],
                'file2' => 'tests/apps/file.txt.zip',
            ]
        );

        $raw_response = $req->send();

        $this->assertRegExp('/\AHTTP\/1\.1 200 OK/', $raw_response);
    }

    public function test_send_for_code_302()
    {
        $req = new Request('GET', 'tests/apps/redirect.php');

        $raw_response = $req->send();

        $this->assertRegExp('/\AHTTP\/1\.1 302 Moved Temporarily/', $raw_response);
    }

    public function test_send_json_for_code_200()
    {
        $req = new Request(
            'POST',
            'tests/apps/json.php',
            '{"hoge":"fuga"}',
            ['content-type' => 'application/json']
        );

        $res = $req->send();

        $this->assertRegExp(
            '/{"input":{"hoge":"fuga"}}/',
            $res
        );
    }

    public function test_cookie_management()
    {
        $fake_session_id = sha1(rand());

        $req = new Request(
            'GET',
            'tests/apps/cookie.php',
            [],
            ['Cookie' => "PHPSESSID={$fake_session_id}"]
        );

        $res1 = $req->send();
        $res2 = $req->send();

        list($headers, $body) = explode("\r\n\r\n", $res2);
        $data = json_decode($body, true);

        $this->assertEquals('2', $data['visited_count']);
    }

    public function test_with_include_path()
    {
        Request::addIncludePath(getcwd().'/tests/apps/include');

        $req = new Request(
            'GET', 'tests/apps/with_include_path.php'
        );

        $res = $req->send();

        $this->assertRegExp('/hogehoge/', $res);
    }
}
