<?php

require __DIR__.'/../vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\TcpServer;

$bridgeUrl = getenv('BRIDGE_HOST') ?: '0.0.0.0';
$bridgePort = getenv('BRIDGE_PORT') ?: '8091';
$guacdHost = getenv('GUACD_HOST') ?: 'guacd';
$guacdPort = getenv('GUACD_PORT') ?: '4822';
$appUrl = getenv('APP_URL') ?: 'http://localhost';
$internalSecret = getenv('INTERNAL_SECRET');

if (! $internalSecret) {
    echo "[ERROR] INTERNAL_SECRET environment variable is required.\n";
    exit(1);
}

$sessions = [];

function internalPost(string $path, array $data): ?array
{
    global $appUrl, $internalSecret;

    $url = rtrim($appUrl, '/').$path;
    $payload = json_encode($data);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n".
                'Content-Length: '.strlen($payload)."\r\n".
                "X-Internal-Secret: $internalSecret\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        echo "[!] internalPost failed for $path\n";

        return null;
    }

    $decoded = json_decode($result, true);
    if (! $decoded) {
        echo "[!] internalPost bad JSON from $path: $result\n";

        return null;
    }

    return $decoded;
}

function guacInstruction(string $opcode, string ...$args): string
{
    $parts = [];
    $parts[] = strlen($opcode).'.'.$opcode;
    foreach ($args as $arg) {
        $parts[] = strlen($arg).'.'.$arg;
    }

    return implode(',', $parts).';';
}

function performHandshake(ConnectionInterface $conn, string $request): ?array
{
    if (! preg_match('/GET (.*?) HTTP/', $request, $matches)) {
        return null;
    }

    $path = $matches[1];
    $query = parse_url($path, PHP_URL_QUERY);
    $token = null;

    if ($query) {
        parse_str($query, $params);
        $token = $params['token'] ?? null;
    }

    $lines = explode("\r\n", $request);
    $wsKey = '';
    $wsProtocol = '';
    foreach ($lines as $line) {
        if (stripos($line, 'Sec-WebSocket-Key:') === 0) {
            $wsKey = trim(substr($line, 18));
        } elseif (stripos($line, 'Sec-WebSocket-Protocol:') === 0) {
            $wsProtocol = trim(substr($line, 24));
        }
    }
    if (! $wsKey) {
        echo "[!] Could not find Sec-WebSocket-Key\n";

        return null;
    }

    $raw = sha1($wsKey.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true);
    $acceptKey = base64_encode($raw);

    $response = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $acceptKey";
    if ($wsProtocol) {
        $response .= "\r\nSec-WebSocket-Protocol: $wsProtocol";
    }
    $response .= "\r\n\r\n";
    $conn->write($response);

    return ['token' => $token];
}

function encodeFrame(string $data): string
{
    $len = strlen($data);
    $frame = chr(0x81);

    if ($len <= 125) {
        $frame .= chr($len);
    } elseif ($len <= 65535) {
        $frame .= chr(126).pack('n', $len);
    } else {
        $frame .= chr(127).pack('J', $len);
    }

    return $frame.$data;
}

function decodeFrame(string $data): ?string
{
    if (strlen($data) < 2) {
        return null;
    }

    $firstByte = ord($data[0]);
    $opcode = $firstByte & 0x0F;
    $secondByte = ord($data[1]);
    $masked = ($secondByte & 0x80) !== 0;
    $payloadLen = $secondByte & 0x7F;
    $offset = 2;

    if ($payloadLen === 126) {
        $payloadLen = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLen === 127) {
        $payloadLen = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }

    if ($opcode === 0x08) {
        return null;
    }

    if ($masked) {
        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = substr($data, $offset, $payloadLen);
        $decoded = '';
        for ($i = 0; $i < $payloadLen; $i++) {
            $decoded .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }

        return $decoded;
    }

    return substr($data, $offset, $payloadLen);
}

function parseGuacInstruction(string $raw): ?array
{
    $parts = explode(',', $raw);
    if (count($parts) < 1) {
        return null;
    }

    $first = $parts[0];
    $dotPos = strpos($first, '.');
    if ($dotPos === false) {
        return null;
    }
    $opcode = substr($first, $dotPos + 1);

    $args = [];
    for ($i = 1; $i < count($parts); $i++) {
        $part = $parts[$i];
        $dotPos = strpos($part, '.');
        if ($dotPos === false) {
            continue;
        }
        $args[] = substr($part, $dotPos + 1);
    }

    return ['opcode' => $opcode, 'args' => $args];
}

function extractGuacInstructions(string $buf): array
{
    $instructions = [];
    $len = strlen($buf);
    $pos = 0;

    while ($pos < $len) {
        $start = $pos;
        $cursor = $pos;
        $complete = false;

        while ($cursor < $len) {
            $dot = strpos($buf, '.', $cursor);
            if ($dot === false) {
                break;
            }

            $sizeStr = substr($buf, $cursor, $dot - $cursor);
            if (! ctype_digit($sizeStr)) {
                break 2;
            }

            $size = (int) $sizeStr;
            $valueEnd = $dot + 1 + $size;

            if ($valueEnd >= $len) {
                break;
            }

            $sep = $buf[$valueEnd];

            if ($sep === ';') {
                $instructions[] = substr($buf, $start, $valueEnd - $start + 1);
                $pos = $valueEnd + 1;
                $complete = true;
                break;
            }

            if ($sep === ',') {
                $cursor = $valueEnd + 1;

                continue;
            }

            break 2;
        }

        if (! $complete) {
            break;
        }
    }

    return [$instructions, substr($buf, $pos)];
}

function buildRdpArgMap(string $host, string $port, string $username, string $password, string $domain): array
{
    return [
        'hostname' => $host,
        'port' => $port,
        'timeout' => '',
        'domain' => $domain,
        'username' => $username,
        'password' => $password,
        'width' => '1920',
        'height' => '1080',
        'dpi' => '96',
        'initial-program' => '',
        'color-depth' => '',
        'disable-audio' => '',
        'enable-printing' => '',
        'printer-name' => '',
        'enable-drive' => '',
        'drive-name' => '',
        'drive-path' => '',
        'create-drive-path' => '',
        'disable-download' => '',
        'disable-upload' => '',
        'console' => '',
        'console-audio' => '',
        'server-layout' => '',
        'security' => 'nla',
        'ignore-cert' => 'true',
        'cert-tofu' => '',
        'cert-fingerprints' => '',
        'disable-auth' => '',
        'remote-app' => '',
        'remote-app-dir' => '',
        'remote-app-args' => '',
        'static-channels' => '',
        'client-name' => 'ServerVault',
        'enable-wallpaper' => '',
        'enable-theming' => '',
        'enable-font-smoothing' => '',
        'enable-full-window-drag' => '',
        'enable-desktop-composition' => '',
        'enable-menu-animations' => '',
        'disable-bitmap-caching' => '',
        'disable-offscreen-caching' => '',
        'disable-glyph-caching' => '',
        'disable-gfx' => '',
        'preconnection-id' => '',
        'preconnection-blob' => '',
        'timezone' => 'UTC',
        'enable-sftp' => '',
        'sftp-hostname' => '',
        'sftp-host-key' => '',
        'sftp-port' => '',
        'sftp-timeout' => '',
        'sftp-username' => '',
        'sftp-password' => '',
        'sftp-private-key' => '',
        'sftp-passphrase' => '',
        'sftp-public-key' => '',
        'sftp-directory' => '',
        'sftp-root-directory' => '',
        'sftp-server-alive-interval' => '',
        'sftp-disable-download' => '',
        'sftp-disable-upload' => '',
        'recording-path' => '',
        'recording-name' => '',
        'recording-exclude-output' => '',
        'recording-exclude-mouse' => '',
        'recording-exclude-touch' => '',
        'recording-include-keys' => '',
        'create-recording-path' => '',
        'recording-write-existing' => '',
        'resize-method' => 'display-update',
        'enable-audio-input' => '',
        'enable-touch' => '',
        'read-only' => '',
        'gateway-hostname' => '',
        'gateway-port' => '',
        'gateway-domain' => '',
        'gateway-username' => '',
        'gateway-password' => '',
        'load-balance-info' => '',
        'disable-copy' => '',
        'disable-paste' => '',
        'wol-send-packet' => '',
        'wol-mac-addr' => '',
        'wol-broadcast-addr' => '',
        'wol-udp-port' => '',
        'wol-wait-time' => '',
        'force-lossless' => '',
        'normalize-clipboard' => '',
    ];
}

$loop = Loop::get();
$connector = new Connector($loop);

$server = new TcpServer("$bridgeUrl:$bridgePort", $loop);

echo "[Guacd Proxy] Listening on ws://$bridgeUrl:$bridgePort\n";

$server->on('connection', function (ConnectionInterface $conn) use (
    $connector,
    $guacdHost,
    $guacdPort,
    &$sessions

) {
    $buffer = '';
    $wsBuf = '';
    $handshakeDone = false;
    $token = null;
    $guacdConn = null;
    $sessionId = null;

    $conn->on('data', function ($data) use (
        $conn,
        &$buffer,
        &$wsBuf,
        &$handshakeDone,
        &$token,
        &$guacdConn,
        &$sessionId,
        $connector,
        $guacdHost,
        $guacdPort,
        &$sessions

    ) {
        if (! $handshakeDone) {
            $buffer .= $data;

            if (str_contains($buffer, "\r\n\r\n")) {
                $result = performHandshake($conn, $buffer);

                if (! $result || ! $result['token']) {
                    echo "[!] Invalid handshake or missing token\n";
                    $conn->end();

                    return;
                }

                $token = $result['token'];
                $handshakeDone = true;
                $buffer = '';

                $sessionData = internalPost('/internal/rdp/validate-token', [
                    'token' => $token,
                ]);

                if (! $sessionData || ! $sessionData['valid']) {
                    echo "[!] Invalid or expired RDP token: $token\n";
                    $conn->write(encodeFrame("Invalid or expired token.\r\n"));
                    $conn->end();

                    return;
                }

                $sessionId = $sessionData['id'];

                $credData = internalPost('/internal/rdp/credentials', [
                    'server_id' => $sessionData['server_id'],
                ]);

                if (! $credData || ! $credData['credentials']) {
                    $conn->write(encodeFrame("Failed to retrieve credentials.\r\n"));
                    $conn->end();

                    return;
                }

                echo "[.] Connecting to guacd ($guacdHost:$guacdPort) for session $sessionId\n";

                $connector->connect("$guacdHost:$guacdPort")->then(
                    function (ConnectionInterface $guacd) use (
                        $conn,
                        &$guacdConn,
                        $sessionData,
                        $credData,
                        $sessionId,
                        &$sessions
                    ) {
                        $guacdConn = $guacd;

                        $host = $sessionData['host'];
                        $port = $sessionData['port'];
                        $username = $sessionData['username'];
                        $domain = $sessionData['domain'] ?? '';
                        $password = $credData['credentials'];

                        echo "[.] Sending Guacamole handshake for $host:$port as $username\n";

                        $guacd->write(guacInstruction('select', 'rdp'));

                        $gBuf = '';
                        $gState = 'select';

                        $guacd->on('data', function ($guacdData) use (
                            $conn,
                            $guacd,
                            &$gBuf,
                            &$gState,
                            $host,
                            $port,
                            $username,
                            $domain,
                            $password,
                            $sessionId,
                            &$sessions
                        ) {
                            $gBuf .= $guacdData;

                            if ($gState === 'select') {
                                [$instrs, $gBuf] = extractGuacInstructions($gBuf);
                                if (! empty($instrs)) {
                                    $instruction = $instrs[0];
                                    echo '[.] guacd raw response: '.preg_replace('/[^\x20-\x7e]/', '.', $instruction)."\n";

                                    $parts = parseGuacInstruction($instruction);
                                    if ($parts && $parts['opcode'] === 'args') {
                                        $version = $parts['args'][0] ?? 'VERSION_1_5_0';
                                        $paramNames = array_slice($parts['args'], 1);
                                        echo '[.] guacd expects '.count($paramNames)." args, version $version\n";

                                        $argMap = buildRdpArgMap($host, (string) $port, $username, $password, $domain);

                                        $guacd->write(guacInstruction('size', '1920', '1080', '96'));
                                        $guacd->write(guacInstruction('audio', 'audio/ogg', 'audio/wav'));
                                        $guacd->write(guacInstruction('image', 'image/png', 'image/jpeg'));
                                        $guacd->write(guacInstruction('timezone', 'UTC'));

                                        $values = array_map(fn ($pname) => $argMap[$pname] ?? '', $paramNames);
                                        $guacd->write(guacInstruction('connect', $version, ...$values));
                                        echo '[.] Sent connect with '.(count($values) + 1).' args (version + '.count($values)." param values)\n";

                                        $gState = 'connecting';
                                        echo "[.] Handshake sent, waiting for guacd response...\n";
                                    } else {
                                        echo '[!] Unexpected guacd opcode: '.($parts['opcode'] ?? 'unknown')."\n";
                                        $conn->write(encodeFrame("Unexpected guacd response.\r\n"));
                                        $guacd->close();
                                        $conn->end();
                                    }
                                }

                                return;
                            }

                            if ($gState === 'connecting') {
                                echo '[.] connecting state, buf='.strlen($gBuf)." bytes\n";

                                [$instrs, $gBuf] = extractGuacInstructions($gBuf);

                                if (! empty($instrs)) {
                                    foreach ($instrs as $instr) {
                                        $parsed = parseGuacInstruction(rtrim($instr, ';'));
                                        if ($parsed && $parsed['opcode'] === 'ready') {
                                            $gState = 'connected';
                                            $conn->write(encodeFrame($instr));
                                            $conn->write(encodeFrame(guacInstruction('size', '0', '1920', '1080')));

                                            internalPost('/internal/rdp/mark-active', [
                                                'session_id' => $sessionId,
                                            ]);

                                            $sessions[$sessionId] = ['guacd' => $guacd, 'conn' => $conn];
                                            echo "[+] RDP session $sessionId established (proper ready)\n";
                                        } else {
                                            $conn->write(encodeFrame($instr));
                                        }
                                    }
                                }

                                if ($gState === 'connecting' && strncmp($gBuf, "ready\n", 6) === 0) {
                                    $gState = 'connected';
                                    $gBuf = substr($gBuf, 6);
                                    $conn->write(encodeFrame('5.ready,1.0;'));

                                    if (strlen($gBuf) > 0) {
                                        [$pending, $gBuf] = extractGuacInstructions($gBuf);
                                        foreach ($pending as $instr) {
                                            $conn->write(encodeFrame($instr));
                                        }
                                    }

                                    internalPost('/internal/rdp/mark-active', [
                                        'session_id' => $sessionId,
                                    ]);

                                    $sessions[$sessionId] = ['guacd' => $guacd, 'conn' => $conn];
                                    echo "[+] RDP session $sessionId established (legacy ready\\n)\n";
                                } elseif ($gState === 'connecting') {
                                    echo '[.] still connecting, buf='.strlen($gBuf)." bytes remaining\n";
                                }

                                return;
                            }

                            if ($gState === 'connected') {
                                [$instrs, $gBuf] = extractGuacInstructions($gBuf);
                                foreach ($instrs as $instr) {
                                    $conn->write(encodeFrame($instr));
                                }
                            }
                        });

                        $guacd->on('close', function () use ($conn, $sessionId, &$sessions) {
                            if ($sessionId && isset($sessions[$sessionId])) {
                                unset($sessions[$sessionId]);
                                internalPost('/internal/rdp/mark-closed', [
                                    'session_id' => $sessionId,
                                ]);
                                echo "[-] RDP session $sessionId closed\n";
                            }
                            try {
                                $conn->end();
                            } catch (Exception $e) {
                            }
                        });
                    },
                    function (Exception $e) use ($conn) {
                        echo '[!] guacd connection failed: '.$e->getMessage()."\n";
                        $conn->write(encodeFrame("Failed to connect to RDP proxy.\r\n"));
                        $conn->end();
                    }
                );
            }

            return;
        }

        if (! $guacdConn) {
            return;
        }

        $wsBuf .= $data;

        while (strlen($wsBuf) >= 2) {
            $secondByte = ord($wsBuf[1]);
            $payloadLen = $secondByte & 0x7F;
            $headerSize = 2;

            if ($payloadLen === 126) {
                if (strlen($wsBuf) < 4) {
                    break;
                }
                $payloadLen = unpack('n', substr($wsBuf, 2, 2))[1];
                $headerSize = 4;
            } elseif ($payloadLen === 127) {
                if (strlen($wsBuf) < 10) {
                    break;
                }
                $payloadLen = unpack('J', substr($wsBuf, 2, 8))[1];
                $headerSize = 10;
            }

            $masked = ($secondByte & 0x80) !== 0;
            $maskSize = $masked ? 4 : 0;
            $frameSize = $headerSize + $maskSize + $payloadLen;

            if (strlen($wsBuf) < $frameSize) {
                break;
            }

            $frame = substr($wsBuf, 0, $frameSize);
            $wsBuf = substr($wsBuf, $frameSize);

            $decoded = decodeFrame($frame);

            if ($decoded === null) {
                echo "[.] Close frame from browser\n";
                $guacdConn->close();

                return;
            }

            if ($decoded !== '') {
                $guacdConn->write($decoded);
            }
        }
    });

    $conn->on('close', function () use (&$guacdConn, &$sessionId, &$sessions) {
        if ($sessionId && isset($sessions[$sessionId])) {
            unset($sessions[$sessionId]);
            internalPost('/internal/rdp/mark-closed', [
                'session_id' => $sessionId,
            ]);
            echo "[-] RDP session $sessionId closed\n";
        }

        if ($guacdConn) {
            try {
                $guacdConn->close();
            } catch (Exception $e) {
            }
        }
    });
});

$loop->run();
