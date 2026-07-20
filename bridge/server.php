<?php

require __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SSH2;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\TcpServer;

$bridgeUrl = getenv('BRIDGE_HOST') ?: '0.0.0.0';
$bridgePort = getenv('BRIDGE_PORT') ?: '8090';
$appUrl = getenv('APP_URL') ?: 'http://localhost';
$internalSecret = getenv('INTERNAL_SECRET');

if (!$internalSecret) {
    echo "[ERROR] INTERNAL_SECRET environment variable is required.\n";
    exit(1);
}

$sessions = [];

function internalPost(string $path, array $data): ?array
{
    global $appUrl, $internalSecret;

    $url = rtrim($appUrl, '/') . $path;
    $payload = json_encode($data);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' =>
                "Content-Type: application/json\r\n" .
                "X-Internal-Secret: $internalSecret\r\n" .
                "Content-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);

    $result = @file_get_contents($url, false, $ctx);

    if ($result === false) {
        return null;
    }

    return json_decode($result, true);
}

function performHandshake(ConnectionInterface $conn, string $request): ?array
{
    if (!preg_match('/GET (.*?) HTTP/', $request, $matches)) {
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
    foreach ($lines as $line) {
        if (stripos($line, 'Sec-WebSocket-Key:') === 0) {
            $wsKey = trim(substr($line, 18));
            break;
        }
    }
    if (!$wsKey) {
        echo "[!] Could not find Sec-WebSocket-Key in " . count($lines) . " lines\n";
        foreach ($lines as $i => $l) {
            echo "  line $i: " . bin2hex($l) . "\n";
        }
        return null;
    }

    $raw = sha1($wsKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true);
    $acceptKey = base64_encode($raw);
    echo "[.] WS: key='$wsKey' accept='$acceptKey'\n";

    $response = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $acceptKey\r\n\r\n";
    echo "[.] WS resp: " . bin2hex($response) . "\n";

    $conn->write($response);

    return ['token' => $token];
}

function encodeFrame(string $data): string
{
    $len = strlen($data);
    $frame = chr(0x82);

    if ($len <= 125) {
        $frame .= chr($len);
    } elseif ($len <= 65535) {
        $frame .= chr(126) . pack('n', $len);
    } else {
        $frame .= chr(127) . pack('J', $len);
    }

    return $frame . $data;
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

$loop = Loop::get();

$server = new TcpServer("$bridgeUrl:$bridgePort", $loop);

echo "[SSH Bridge] Listening on ws://$bridgeUrl:$bridgePort\n";

$server->on('connection', function (ConnectionInterface $conn) use (
    &$sessions,
    $loop
) {
    $buffer = '';
    $handshakeDone = false;
    $ssh = null;
    $sessionId = null;
    $token = null;

    $conn->on('data', function ($data) use (
        $conn,
        &$buffer,
        &$handshakeDone,
        &$ssh,
        &$sessionId,
        &$token,
        $loop
    ) {
        if (!$handshakeDone) {
            $buffer .= $data;

            if (str_contains($buffer, "\r\n\r\n")) {
                echo "[.] RAW request lines:\n";
                foreach (explode("\r\n", $buffer) as $i => $l) {
                    if ($l !== '') echo "  L$i: $l\n";
                }

                $result = performHandshake($conn, $buffer);

                if (!$result || !$result['token']) {
                    echo "[!] Invalid handshake or missing token\n";
                    $conn->end();
                    return;
                }

                $token = $result['token'];
                $handshakeDone = true;
                $buffer = '';

                $sessionData = internalPost('/internal/ssh/validate-token', [
                    'token' => $token,
                ]);

                if (!$sessionData || !$sessionData['valid']) {
                    echo "[!] Invalid or expired token: $token\n";
                    $conn->write(encodeFrame("\x1b[31mConnection rejected: invalid or expired token.\x1b[0m\r\n"));
                    $conn->end();
                    return;
                }

                $sessionId = $sessionData['id'];

                $credData = internalPost('/internal/ssh/credentials', [
                    'server_id' => $sessionData['server_id'],
                ]);

                if (!$credData || !$credData['credentials']) {
                    $conn->write(encodeFrame("\x1b[31mFailed to retrieve credentials.\x1b[0m\r\n"));
                    $conn->end();
                    return;
                }

                    try {
                        $ssh = new SSH2($sessionData['host'], $sessionData['port'], 10);

                        $loginUsername = $sessionData['username'];
                        $loginPassword = $credData['credentials'];
                        echo "[.] Connecting to {$sessionData['host']}:{$sessionData['port']} as '$loginUsername' (pass_len=" . strlen($loginPassword ?? '') . ")\n";

                        if (!$ssh->login($loginUsername, $loginPassword)) {
                            echo "[!] Auth failed for session $sessionId\n";
                            $conn->write(encodeFrame("\x1b[31mAuthentication failed.\x1b[0m\r\n"));
                            $conn->end();
                            return;
                        }

                        $ssh->enablePTY();
                        $ssh->setTimeout(5);

                        try {
                            $initial = $ssh->read();
                        } catch (\Exception $e) {
                            echo "[!] Shell init error: " . $e->getMessage() . "\n";
                            $conn->write(encodeFrame("\x1b[31mShell init error: " . $e->getMessage() . "\x1b[0m\r\n"));
                            $conn->end();
                            return;
                        }

                        $ssh->setTimeout(0.05);
                        $sessions[$sessionId] = ['ssh' => $ssh, 'conn' => $conn];
                        internalPost('/internal/ssh/mark-active', [
                            'session_id' => $sessionId,
                        ]);
                        echo "[+] Session $sessionId established\n";

                        if ($initial !== '' && $initial !== null) {
                            $conn->write(encodeFrame($initial));
                        }

                        $loop->addPeriodicTimer(0.05, function () use ($sessionId, &$sessions) {
                            $entry = $sessions[$sessionId] ?? null;
                            if (!$entry) return;

                            try {
                                $output = $entry['ssh']->read();
                                if ($output !== '' && $output !== null) {
                                    $entry['conn']->write(encodeFrame($output));
                                }
                            } catch (\Exception $e) {
                                // connection closed
                            }
                        });
                } catch (\Exception $e) {
                    echo "[!] SSH connection failed: " . $e->getMessage() . "\n";
                    $conn->write(encodeFrame("\x1b[31mConnection failed: " . $e->getMessage() . "\x1b[0m\r\n"));
                    $conn->end();
                }
            }
            return;
        }

        $decoded = decodeFrame($data);

        if ($decoded === null) {
            // Close frame received
            if ($ssh) {
                $ssh->disconnect();
            }
            return;
        }

        if ($ssh && $decoded !== '') {
            try {
                $ssh->write($decoded);
            } catch (\Exception $e) {
                // connection closed
            }
        }
    });

    $conn->on('close', function () use (&$ssh, &$sessionId, &$sessions) {
        if ($sessionId && isset($sessions[$sessionId])) {
            unset($sessions[$sessionId]);
            internalPost('/internal/ssh/mark-closed', [
                'session_id' => $sessionId,
            ]);
            echo "[-] Session $sessionId closed\n";
        }

        if ($ssh) {
            try {
                $ssh->disconnect();
            } catch (\Exception $e) {
                // already disconnected
            }
        }
    });
});

$loop->run();
