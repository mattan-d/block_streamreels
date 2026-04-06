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
 * Vertical reels viewer: swipe / tap sides / wheel / keyboard (Facebook-style).
 *
 * @module block_streamreels/reels
 * @copyright  2026 CentricApp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    const SWIPE_THRESHOLD = 44;
    const WHEEL_THRESHOLD = 72;

    /**
     * Append autoplay query per Stream embed docs: autoplay=1 (or true); player applies muted/playsinline when set.
     *
     * @param {string} baseUrl From data-embed-src (unchanged in DOM; we only mutate for the live iframe src).
     * @returns {string}
     */
    function embedUrlForActiveSlide(baseUrl) {
        if (!baseUrl) {
            return baseUrl;
        }
        try {
            const u = new URL(baseUrl);
            if (!u.searchParams.has('autoplay')) {
                u.searchParams.set('autoplay', '1');
            }
            return u.toString();
        } catch (e) {
            const sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
            return baseUrl + sep + 'autoplay=1';
        }
    }

    /**
     * @param {HTMLElement} slide
     */
    function loadSlideMedia(slide) {
        const iframe = slide.querySelector('iframe.streamreels-embed');
        if (!iframe) {
            return;
        }
        const src = iframe.getAttribute('data-embed-src');
        if (!src) {
            return;
        }
        const current = iframe.getAttribute('src');
        if (!current || current === 'about:blank') {
            iframe.setAttribute('src', embedUrlForActiveSlide(src));
        }
    }

    /**
     * @param {HTMLElement} slide
     */
    function unloadSlideMedia(slide) {
        const iframe = slide.querySelector('iframe.streamreels-embed');
        if (iframe) {
            iframe.setAttribute('src', 'about:blank');
        }
    }

    /**
     * @param {HTMLElement} viewer
     * @param {number} index
     */
    function goTo(viewer, index) {
        const slides = viewer.querySelectorAll('.streamreels-slide');
        const n = slides.length;
        if (n === 0) {
            return;
        }
        const i = Math.max(0, Math.min(index, n - 1));
        const track = viewer.querySelector('.streamreels-track');
        if (track) {
            track.style.transform = 'translateY(-' + (i * 100) + '%)';
        }
        slides.forEach(function(s, idx) {
            s.classList.toggle('is-active', idx === i);
            s.setAttribute('aria-hidden', idx === i ? 'false' : 'true');
            if (idx === i) {
                loadSlideMedia(s);
            } else {
                unloadSlideMedia(s);
            }
        });
        viewer.dataset.currentIndex = String(i);

        const currentEl = viewer.querySelector('.streamreels-counter-current');
        if (currentEl) {
            currentEl.textContent = String(i + 1);
        }
        const bar = viewer.querySelector('.streamreels-progress-bar');
        if (bar) {
            bar.style.height = ((i + 1) / n * 100) + '%';
        }

        const prevBtn = viewer.querySelector('.streamreels-nav-prev');
        const nextBtn = viewer.querySelector('.streamreels-nav-next');
        if (prevBtn) {
            prevBtn.disabled = i <= 0;
        }
        if (nextBtn) {
            nextBtn.disabled = i >= n - 1;
        }
    }

    /**
     * @param {HTMLElement} viewer
     * @param {number} delta
     */
    function navigate(viewer, delta) {
        const i = parseInt(viewer.dataset.currentIndex || '0', 10);
        goTo(viewer, i + delta);
    }

    /**
     * @param {string} rootId
     */
    function init(rootId) {
        const viewer = document.getElementById(rootId);
        if (!viewer) {
            return;
        }

        const slides = viewer.querySelectorAll('.streamreels-slide');
        const n = slides.length;
        if (n === 0) {
            return;
        }

        let touchStartY = null;

        viewer.addEventListener('touchstart', function(e) {
            touchStartY = e.changedTouches[0].screenY;
        }, {passive: true});

        viewer.addEventListener('touchend', function(e) {
            if (touchStartY === null) {
                return;
            }
            const dy = touchStartY - e.changedTouches[0].screenY;
            touchStartY = null;
            if (dy > SWIPE_THRESHOLD) {
                navigate(viewer, 1);
            } else if (dy < -SWIPE_THRESHOLD) {
                navigate(viewer, -1);
            }
        }, {passive: true});

        const nextBtn = viewer.querySelector('.streamreels-nav-next');
        const prevBtn = viewer.querySelector('.streamreels-nav-prev');
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                navigate(viewer, 1);
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                navigate(viewer, -1);
            });
        }

        // Tap sides (like mobile reels); mirror in RTL — skip interactive controls.
        viewer.addEventListener('click', function(e) {
            if (e.target.closest('a, button, iframe, input, textarea, select')) {
                return;
            }
            const rect = viewer.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const w = rect.width;
            const rtl = window.getComputedStyle(viewer).direction === 'rtl';
            const onNext = rtl ? (x < w * 0.32) : (x > w * 0.68);
            const onPrev = rtl ? (x > w * 0.68) : (x < w * 0.32);
            if (onNext) {
                navigate(viewer, 1);
            } else if (onPrev) {
                navigate(viewer, -1);
            }
        });

        let wheelAccum = 0;
        let wheelTimeout = null;
        viewer.addEventListener('wheel', function(e) {
            wheelAccum += e.deltaY;
            if (wheelTimeout) {
                clearTimeout(wheelTimeout);
            }
            wheelTimeout = setTimeout(function() {
                wheelAccum = 0;
            }, 220);
            if (wheelAccum > WHEEL_THRESHOLD) {
                navigate(viewer, 1);
                wheelAccum = 0;
            } else if (wheelAccum < -WHEEL_THRESHOLD) {
                navigate(viewer, -1);
                wheelAccum = 0;
            }
        }, {passive: true});

        viewer.setAttribute('tabindex', '0');
        viewer.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'PageDown') {
                e.preventDefault();
                navigate(viewer, 1);
            } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
                e.preventDefault();
                navigate(viewer, -1);
            }
        });

        goTo(viewer, 0);
    }

    return {
        init: init
    };
});
