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
 * Stream reels block (uses {@see local_stream} settings only).
 *
 * @package    block_streamreels
 * @copyright  2026 CentricApp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_streamreels\local\reels_client;

/**
 * Block class.
 */
class block_streamreels extends block_base {

    /**
     * Max cards shown after merging teachers' reels.
     */
    private const DISPLAY_LIMIT = 24;

    public function init(): void {
        $this->title = get_string('pluginname', 'block_streamreels');
    }

    public function has_config(): bool {
        return false;
    }

    public function applicable_formats(): array {
        return [
            'course' => true,
            'mod' => false,
            'my' => false,
            'tag' => false,
        ];
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function get_content() {
        global $CFG, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        require_once($CFG->libdir . '/filelib.php');

        $config = get_config('local_stream');
        if (empty($config->streamurl) || empty($config->streamkey)) {
            if (has_capability('moodle/site:config', context_system::instance())) {
                $this->content->text = html_writer::div(
                    get_string('adminmissingstreamconfig', 'block_streamreels'),
                    'alert alert-warning'
                );
            }
            return $this->content;
        }

        $course = $this->page->course;
        if (empty($course->id) || $course->id == SITEID) {
            return $this->content;
        }

        $context = context_course::instance($course->id);
        $emails = $this->get_teacher_emails($context, $DB);
        if (!$emails) {
            $this->content->text = html_writer::div(get_string('noteachers', 'block_streamreels'), 'text-muted');
            return $this->content;
        }

        $cache = cache::make('block_streamreels', 'reelsfeed');
        $cachekey = 'c' . (int) $course->id . '_' . md5(implode(',', $emails));
        $reels = $cache->get($cachekey);
        if ($reels === false) {
            $reels = $this->load_reels_for_emails($config->streamurl, $config->streamkey, $emails);
            $cache->set($cachekey, $reels);
        }

        if (!$reels) {
            $this->content->text = html_writer::div(get_string('noreels', 'block_streamreels'), 'text-muted');
            return $this->content;
        }

        $this->page->requires->css('/blocks/streamreels/styles.css');

        $itemshtml = '';
        foreach (array_slice($reels, 0, self::DISPLAY_LIMIT) as $item) {
            $itemshtml .= $this->render_reel_card($item);
        }

        $this->content->text = html_writer::div(
            html_writer::div($itemshtml, 'streamreels-rail'),
            'streamreels-block'
        );

        return $this->content;
    }

    /**
     * Unique non-empty emails for teacher + editingteacher in the course.
     *
     * @param \context $context
     * @param \moodle_database $db
     * @return string[]
     */
    private function get_teacher_emails(\context $context, \moodle_database $db): array {
        $shortnames = ['editingteacher', 'teacher'];
        $roleids = [];
        foreach ($shortnames as $shortname) {
            if ($role = $db->get_record('role', ['shortname' => $shortname])) {
                $roleids[] = (int) $role->id;
            }
        }
        if (!$roleids) {
            return [];
        }

        $users = get_role_users($roleids, $context, true, 'ra.id, u.id, u.email');
        $emails = [];
        foreach ($users as $user) {
            $raw = trim($user->email ?? '');
            if ($raw === '' || !validate_email($raw)) {
                continue;
            }
            $emails[strtolower($raw)] = $raw;
        }
        return array_values($emails);
    }

    /**
     * @param string $baseurl
     * @param string $apikey
     * @param string[] $emails
     * @return array<int,array<string,mixed>>
     */
    private function load_reels_for_emails(string $baseurl, string $apikey, array $emails): array {
        $merged = [];
        foreach ($emails as $email) {
            $batch = reels_client::fetch_by_email($baseurl, $apikey, $email, reels_client::LIMIT_PER_TEACHER);
            foreach ($batch as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = $row['id'] ?? null;
                if ($id === null || $id === '') {
                    continue;
                }
                $key = (string) $id;
                $merged[$key] = $row;
            }
        }

        $list = array_values($merged);
        usort($list, static function (array $a, array $b): int {
            $ta = (int) ($a['timecreated'] ?? 0);
            $tb = (int) ($b['timecreated'] ?? 0);
            return $tb <=> $ta;
        });
        return $list;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function render_reel_card(array $item): string {
        $title = isset($item['title']) ? format_string($item['title'], true) : get_string('untitled', 'block_streamreels');
        $watch = $item['watch_url'] ?? '';
        if (!$watch) {
            return '';
        }
        $thumb = $item['thumbnail'] ?? '';
        $duration = isset($item['duration']) ? s($item['duration']) : '';

        $meta = $duration !== '' ? html_writer::span($duration, 'streamreels-duration') : '';

        $img = '';
        if ($thumb) {
            $img = html_writer::empty_tag('img', [
                'src' => $thumb,
                'alt' => $title,
                'class' => 'streamreels-thumb',
                'loading' => 'lazy',
            ]);
        } else {
            $img = html_writer::div($title, 'streamreels-placeholder');
        }

        $inner = $img . html_writer::div($title . $meta, 'streamreels-caption');
        $link = html_writer::link($watch, $inner, [
            'class' => 'streamreels-card',
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]);

        return html_writer::div($link, 'streamreels-item');
    }
}
