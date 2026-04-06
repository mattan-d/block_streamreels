<?php
// This file is part of Moodle - http://moodle.org/
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
 * Stream user-reels API client (uses local_stream streamurl + streamkey).
 *
 * @package    block_streamreels
 * @copyright  2026 CentricApp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_streamreels\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Fetches reels for a Stream user identified by email.
 */
class reels_client {

    /** @var int Max reels per teacher before merge */
    public const LIMIT_PER_TEACHER = 30;

    /**
     * GET /webservice/api/user-reels (same auth model as user-videos).
     *
     * @param string $baseurl Stream base URL from local_stream.
     * @param string $apikey Bearer token from local_stream.
     * @param string $email Teacher email on Stream.
     * @param int $limit 1–100
     * @return array<int,array<string,mixed>> List of video/reel records.
     */
    public static function fetch_by_email(string $baseurl, string $apikey, string $email, int $limit = self::LIMIT_PER_TEACHER): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $baseurl = rtrim($baseurl, '/');
        $email = trim($email);
        if ($email === '' || $apikey === '') {
            return [];
        }

        $limit = min(100, max(1, $limit));

        $url = $baseurl . '/webservice/api/user-reels';
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $apikey,
            'Accept: application/json',
        ]);
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 20,
        ];

        $params = [
            'email' => $email,
            'limit' => $limit,
            'offset' => 0,
        ];

        $json = $curl->get($url, $params, $options);
        $data = json_decode($json, true);
        if (!is_array($data) || !empty($data['error'])) {
            return [];
        }

        $items = $data['videos'] ?? $data['reels'] ?? [];
        return is_array($items) ? $items : [];
    }
}
