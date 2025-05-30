<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Websocket client to monitor evaluations.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion;

/**
 * Websocket client implementation, compliant with RFC6455 standard.
 *
 * Inspired form paragi's PHP-websocket-client: https://github.com/paragi/PHP-websocket-client
 * @see        https://www.rfc-editor.org/rfc/rfc6455
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class websocket {

    /**
     * @var resource The websocket handle.
     */
    protected $handle;

    /**
     * @var string Host server name.
     */
    protected $server;

    /**
     * Create a websocket client.
     * @param string $server The server to connect to.
     * @param string|number $port The port to use.
     * @param string $protocol The protocol to use (typically 'tcp' or 'ssl').
     */
    public function __construct($server, $port, $protocol) {
        $errorcode = '';
        $errormessage = '';
        $wshandle = stream_socket_client("$protocol://$server:$port", $errorcode, $errormessage);
        if ($wshandle === false) {
            throw new websocket_exception('wsconnectionerror', $errorcode, $errormessage);
        }
        $this->handle = $wshandle;
        $this->server = $server;
    }

    /**
     * Open the websocket communication by handshake.
     * @param string $path The path of the resource on the server.
     */
    public function open($path = '/') {
        // Key must be 16 characters long before encoding.
        $wskey = base64_encode(openssl_random_pseudo_bytes(16));

        // Initiate handshake.
        // Each line must end with CRLF.
        // Handshake must end with a line containing only CRLF.
        fwrite($this->handle,
            "GET $path HTTP/1.1\r\n" .
            "Connection: Upgrade\r\n" .
            "Upgrade: websocket\r\n" .
            "Host: $this->server\r\n" .
            "Sec-WebSocket-Key: $wskey\r\n" .
            "Sec-WebSocket-Version: 13\r\n" .
            "\r\n"
        );

        // Read first line of handshake response.
        // It should be "HTTP<version> 101 <reason>\r\n".
        $line = fgets($this->handle);
        if ($line === false || strlen(trim($line)) == 0 || !preg_match('#^HTTP[^ ]+ 101 [^\\r\\n]*\\r\\n$#', $line)) {
            throw new websocket_exception('wshandshakeerror', 'SERVER_HANDSHAKE_FIRSTLINE', json_encode($line));
        }

        // Read remaining of handshake response.
        // We search for response key.
        // We expect a key built as follows. The string constant is a standard.
        $expectedkey = base64_encode(sha1($wskey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $keyok = false;
        while (($line = fgets($this->handle)) !== false) {
            if ($line == "\r\n") {
                // End of handshake response.
                if (!$keyok) {
                    throw new websocket_exception('wshandshakeerror', 'SERVER_NO_HANDSHAKE_KEY');
                } else {
                    // Everything is good!
                    break;
                }
            }
            $matches = null;
            if (preg_match('#^Sec-WebSocket-Accept: ([^\\r\\n]*)\\r\\n$#', $line, $matches)) {
                if ($matches[1] === $expectedkey) {
                    // Key is correct.
                    $keyok = true;
                } else {
                    throw new websocket_exception('wshandshakeerror', 'SERVER_BAD_HANDSHAKE_KEY');
                }
            }
        }
    }

    /**
     * Read frames from the websocket and return the first full message.
     * This method handles control frames by replying and continuing to read until a full message is recieved.
     * @return string|false The recieved message, or false if the connection was closed.
     */
    public function read_next_message() {
        if (!is_resource($this->handle)) {
            return false;
        }

        $message = '';

        do {
            // Read frame header.
            $header = fread($this->handle, 2);
            if (!$header) {
                throw new websocket_exception('wsreaderror', 'FRAME_HEADER_FAILED');
            }

            $opcode = ord($header[0]) & 0x0F;
            $final = ord($header[0]) & 0x80;
            $masked = ord($header[1]) & 0x80;
            $payloadlength = ord($header[1]) & 0x7F;
            $mask = null;

            // Read payload length extensions.
            $extensionlength = 0;
            if ($payloadlength >= 0x7E) {
                $extensionlength = 2;
                if ($payloadlength == 0x7F) {
                    $extensionlength = 8;
                }
                $header = fread($this->handle, $extensionlength);
                if (!$header) {
                    throw new websocket_exception('wsreaderror', 'FRAME_HEADER_EXT_FAILED');
                }

                // Set extended payload length.
                $payloadlength = unpack($extensionlength == 8 ? 'J' : 'n', $header)[1];
            }

            // Read masking key.
            if ($masked) {
                $mask = fread($this->handle, 4);
                if (!$mask) {
                    throw new websocket_exception('wsreaderror', 'FRAME_HEADER_MASK_FAILED');
                }
            }

            // Read payload.
            $payload = '';
            $remaining = $payloadlength;
            while ($remaining > 0) {
                $frame = fread($this->handle, $remaining);
                if (!$frame) {
                    throw new websocket_exception('wsreaderror', 'FRAME_PAYLOAD_FAILED');
                }
                $remaining -= strlen($frame);
                $payload .= $frame;
            }

            if ($masked) {
                // Unmask data.
                $payload = self::mask_unmask($payload, $mask);
            }

            if ($opcode == 9) { // Ping.
                // Send pong and continue to read.
                // Pong data must be ping data (masked).
                $mask = self::generate_mask();
                $pongdata = self::mask_unmask($payload, $mask);
                fwrite($this->handle, chr(0x8A) . chr(0x80 | $payloadlength) . $mask . $pongdata);
                continue;
            } else if ($opcode == 8) { // Close.
                $this->close();
                if ($final) {
                    return $message;
                } else {
                    return false;
                }
            } else if ($opcode < 3) { // Data frame.
                $message .= $payload;
            } else {
                continue;
            }
        } while (!$final);

        return $message;
    }

    /**
     * Close the websocket by sending a close control frame.
     * Does nothing if the websocket was already closed.
     */
    public function close() {
        if (is_resource($this->handle)) {
            // Send close frame.
            fwrite($this->handle, chr(0x88) . chr(0x80) . self::generate_mask());
            // Close resource.
            fclose($this->handle);
        }
    }

    /**
     * Generate a pseudo-random secure mask for sending data through the websocket.
     * @return string The 4-bytes mask.
     */
    private static function generate_mask() {
        return openssl_random_pseudo_bytes(4);
    }

    /**
     * Mask (or unmask) data. This is the same method because operation is symmetric.
     * @param string $originaldata The data to mask or unmask.
     * @param string $mask The mask to use.
     * @return string Transformed data.
     */
    private static function mask_unmask($originaldata, $mask) {
        $transformed = '';
        $datalength = strlen($originaldata);
        for ($i = 0; $i < $datalength; $i++) {
            $transformed .= $originaldata[$i] ^ $mask[$i % 4];
        }
        return $transformed;
    }
}
