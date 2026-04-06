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

        $slice = array_slice($reels, 0, self::DISPLAY_LIMIT);
        $viewerid = 'streamreels-viewer-' . (int) $this->instance->id;

        $slideshtml = '';
        $slideindex = 0;
        foreach ($slice as $item) {
            $slide = $this->render_reel_slide($item, $slideindex);
            if ($slide === '') {
                continue;
            }
            $slideshtml .= $slide;
            $slideindex++;
        }

        if ($slideindex === 0) {
            $this->content->text = html_writer::div(get_string('noreels', 'block_streamreels'), 'text-muted');
            return $this->content;
        }

        $total = $slideindex;

        $progress = html_writer::div(html_writer::div('', ['class' => 'streamreels-progress-bar']), 'streamreels-progress');

        $counter = html_writer::div(
            html_writer::span('1', 'streamreels-counter-current')
            . ' / '
            . html_writer::span((string) $total, 'streamreels-counter-total'),
            'streamreels-counter'
        );

        $nav = html_writer::div(
            html_writer::tag(
                'button',
                '↑',
                [
                    'type' => 'button',
                    'class' => 'streamreels-nav streamreels-nav-prev',
                    'aria-label' => get_string('previousreel', 'block_streamreels'),
                    'disabled' => 'disabled',
                ]
            )
            . html_writer::tag(
                'button',
                '↓',
                [
                    'type' => 'button',
                    'class' => 'streamreels-nav streamreels-nav-next',
                    'aria-label' => get_string('nextreel', 'block_streamreels'),
                ]
            ),
            'streamreels-nav-col'
        );

        $track = html_writer::div($slideshtml, 'streamreels-track');

        $viewer = html_writer::div(
            $counter . $progress . $nav . $track,
            'streamreels-viewer',
            [
                'id' => $viewerid,
                'role' => 'region',
                'aria-label' => get_string('viewerregion', 'block_streamreels'),
            ]
        );

        $hint = html_writer::div(get_string('navigationshint', 'block_streamreels'), 'streamreels-hint');

        $this->page->requires->js_call_amd('block_streamreels/reels', 'init', [$viewerid]);

        $this->content->text = html_writer::div($viewer . $hint, 'streamreels-block');

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
                $id = $this->reel_field_string($row['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $merged[$id] = $row;
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
     * Normalise API fields that may be string, number, or occasionally nested structures.
     *
     * @param mixed $value
     */
    private function reel_field_string($value, int $depth = 0): string {
        if ($depth > 4) {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            foreach ($value as $nested) {
                $s = $this->reel_field_string($nested, $depth + 1);
                if ($s !== '') {
                    return $s;
                }
            }
            return '';
        }
        return '';
    }

    /**
     * One vertical slide (embed or fallback link).
     *
     * @param array<string,mixed> $item
     */
    private function render_reel_slide(array $item, int $index): string {
        $titleraw = $this->reel_field_string($item['title'] ?? '');
        $title = $titleraw !== ''
            ? format_string($titleraw, true)
            : get_string('untitled', 'block_streamreels');

        $watch = $this->reel_field_string($item['watch_url'] ?? '');
        $embed = $this->reel_field_string($item['embed_url'] ?? '');
        $thumb = $this->reel_field_string($item['thumbnail'] ?? '');

        $durationraw = $this->reel_field_string($item['duration'] ?? '');
        $duration = $durationraw !== '' ? s($durationraw) : '';

        $mediaattrs = ['class' => 'streamreels-slide-media'];
        if ($thumb !== '') {
            $mediaattrs['style'] = 'background-image:url("' . s($thumb) . '");';
        }

        $media = '';
        if ($embed !== '') {
            $media = html_writer::tag('iframe', '', [
                'class' => 'streamreels-embed',
                'title' => $title,
                'allowfullscreen' => 'true',
                'allow' => 'autoplay; encrypted-media; picture-in-picture; fullscreen',
                'data-embed-src' => $embed,
                'src' => 'about:blank',
            ]);
        } else if ($watch !== '') {
            $img = $thumb !== ''
                ? html_writer::empty_tag('img', [
                    'src' => $thumb,
                    'alt' => '',
                    'class' => 'streamreels-fallback-thumb',
                    'loading' => 'lazy',
                ])
                : html_writer::div($title, 'streamreels-slide-title');
            $media = html_writer::link($img, $watch, [
                'class' => 'streamreels-fallback-link',
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]);
        } else {
            return '';
        }

        $metabits = html_writer::div($title, 'streamreels-slide-title');
        if ($duration !== '') {
            $metabits .= html_writer::div($duration, 'streamreels-duration');
        }
        if ($watch !== '') {
            $metabits .= html_writer::link(
                get_string('openinstream', 'block_streamreels'),
                $watch,
                ['class' => 'streamreels-external', 'target' => '_blank', 'rel' => 'noopener noreferrer']
            );
        }

        $meta = html_writer::div($metabits, 'streamreels-slide-meta');

        return html_writer::div(
            html_writer::div($media, '', $mediaattrs) . $meta,
            'streamreels-slide',
            [
                'data-index' => (string) $index,
                'aria-hidden' => $index === 0 ? 'false' : 'true',
            ]
        );
    }
}
