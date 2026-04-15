/**
 * propovedi.js – Sermons video page
 *
 * Uses the YouTube IFrame Player API to embed the playlist,
 * and fetches the video list via a CORS-friendly proxy of the
 * YouTube RSS feed. Falls back to the playlist embed if the
 * feed is unavailable.
 *
 * Playlist: PLWG1YRkVOS2Lsy7tcgrt92KgLELY3044r
 */
(function () {
  'use strict';

  var PLAYLIST_ID = 'PLWG1YRkVOS2Lsy7tcgrt92KgLELY3044r';
  var FIRST_VIDEO = 'EMiZJ1JZF_Q';

  var nowTitle    = document.getElementById('now-title');
  var gridEl      = document.getElementById('video-grid');

  var ytPlayer    = null;
  var currentId   = '';
  var videos      = [];

  /* ── YouTube IFrame API ──────────────────── */

  // Load the API script
  var tag = document.createElement('script');
  tag.src = 'https://www.youtube.com/iframe_api';
  var firstScript = document.getElementsByTagName('script')[0];
  firstScript.parentNode.insertBefore(tag, firstScript);

  // Called by YouTube API when ready
  window.onYouTubeIframeAPIReady = function () {
    ytPlayer = new YT.Player('video-player', {
      height: '100%',
      width: '100%',
      videoId: FIRST_VIDEO,
      playerVars: {
        list: PLAYLIST_ID,
        listType: 'playlist',
        rel: 0,
        modestbranding: 1,
        hl: 'bg'
      },
      events: {
        onReady: onPlayerReady,
        onStateChange: onPlayerStateChange
      }
    });
  };

  function onPlayerReady() {
    // Try to get playlist data from the player itself
    setTimeout(extractPlaylistFromPlayer, 2000);
  }

  function onPlayerStateChange(event) {
    // When a new video starts playing, update the "now playing" info
    if (event.data === YT.PlayerState.PLAYING || event.data === YT.PlayerState.CUED) {
      updateNowPlaying();
    }
  }

  function updateNowPlaying() {
    if (!ytPlayer || typeof ytPlayer.getVideoData !== 'function') return;
    var data = ytPlayer.getVideoData();
    if (data && data.title) {
      nowTitle.textContent = data.title;
      currentId = data.video_id || '';
      highlightActive(currentId);
    }
  }

  /* ── Extract playlist from player ────────── */

  function extractPlaylistFromPlayer() {
    if (!ytPlayer || typeof ytPlayer.getPlaylist !== 'function') return;

    var playlist = ytPlayer.getPlaylist();
    if (!playlist || !playlist.length) {
      // Retry once more after a delay
      setTimeout(function () {
        var pl = ytPlayer.getPlaylist();
        if (pl && pl.length) {
          buildVideoGrid(pl);
        }
      }, 3000);
      return;
    }

    buildVideoGrid(playlist);
  }

  /* ── Build video grid from video IDs ─────── */

  function buildVideoGrid(videoIds) {
    // Reverse: newest first (YouTube playlists are oldest-first by default)
    // But this playlist may already be newest-first, so we keep order and
    // let the user see them as the channel owner arranged them.
    videos = videoIds;

    gridEl.innerHTML = '';

    videos.forEach(function (videoId, index) {
      var card = document.createElement('div');
      card.className = 'video-card';
      card.setAttribute('data-video-id', videoId);

      card.innerHTML =
        '<div class="video-thumb">' +
          '<img src="https://img.youtube.com/vi/' + videoId + '/mqdefault.jpg" ' +
               'alt="Видео" loading="lazy">' +
          '<div class="video-play-icon"></div>' +
        '</div>' +
        '<div class="video-card-body">' +
          '<span class="video-index">#' + (index + 1) + '</span>' +
          '<h3 id="title-' + videoId + '">Проповед</h3>' +
        '</div>';

      card.addEventListener('click', function () {
        playVideo(videoId, index);
      });

      gridEl.appendChild(card);

      // Try to fetch title via noembed (CORS-friendly)
      fetchVideoTitle(videoId);
    });

    // Highlight the first one
    updateNowPlaying();
  }

  /* ── Fetch video title via noembed ───────── */

  function fetchVideoTitle(videoId) {
    var url = 'https://noembed.com/embed?url=https://www.youtube.com/watch?v=' + videoId;

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.title) {
          var el = document.getElementById('title-' + videoId);
          if (el) el.textContent = data.title;

          // Update now-playing if this is the current video
          if (videoId === currentId && nowTitle) {
            nowTitle.textContent = data.title;
          }
        }
      })
      .catch(function () {
        // Ignore — keep the generic "Проповед" title
      });
  }

  /* ── Play a specific video ──────────────── */

  function playVideo(videoId, index) {
    if (!ytPlayer) return;

    if (typeof ytPlayer.playVideoAt === 'function' && typeof index === 'number') {
      ytPlayer.playVideoAt(index);
    } else if (typeof ytPlayer.loadVideoById === 'function') {
      ytPlayer.loadVideoById(videoId);
    }

    currentId = videoId;
    highlightActive(videoId);

    // Scroll to the player
    var wrap = document.querySelector('.video-player-wrap');
    if (wrap) {
      wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Update now-playing with the title we may already have
    var titleEl = document.getElementById('title-' + videoId);
    if (titleEl) {
      nowTitle.textContent = titleEl.textContent;
    }
  }

  /* ── Highlight active card ──────────────── */

  function highlightActive(videoId) {
    var cards = gridEl.querySelectorAll('.video-card');
    cards.forEach(function (card) {
      if (card.getAttribute('data-video-id') === videoId) {
        card.classList.add('active');
      } else {
        card.classList.remove('active');
      }
    });
  }

})();

