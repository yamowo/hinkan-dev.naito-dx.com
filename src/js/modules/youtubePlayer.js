/**
 * YouTubeプレイヤーを初期化する関数
 * @function initYouTubePlayer
 * @description YouTubeリンクがクリックされた際にオーバーレイを表示し、YouTube動画を再生するプレイヤーを生成します。
 * モバイル端末かどうかの判定を行い、端末に応じた処理を行います。
 */
export const initYouTubePlayer = () => {
  let player; // YouTubeプレイヤーのインスタンス
  let currentVideoId; // 現在再生中のYouTubeビデオID
  const youtubeLinks = document.querySelectorAll('figure.youtube-link a'); // YouTubeリンクの要素を取得
  const overlay = document.getElementById('p-youtubePlayer'); // オーバーレイ要素を取得
  const videoContainer = document.querySelector('.p-youtubePlayer__container'); // ビデオコンテナ要素を取得
  const videoWrapper = document.querySelector('.p-youtubePlayer__wrapper'); // ビデオラッパー要素を取得
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent); // モバイル端末かどうかを判定

  // 必要な要素が見つからない場合はエラーメッセージを出力し処理を終了
  if (!overlay || !videoContainer || !videoWrapper) {
      console.error('Required elements not found');
      return;
  }

  /**
   * YouTubeリンクがクリックされた際の処理
   * @function handleLinkClick
   * @param {Event} e - クリックイベント
   * @description リンクのクリックを無効化し、ビデオIDを抽出してオーバーレイを表示します。
   */
  const handleLinkClick = function(e) {
      e.preventDefault(); // デフォルトのリンク動作を無効化
      const videoId = extractVideoId(this.href); // リンクURLからビデオIDを抽出
      if (videoId) {
          currentVideoId = videoId; // 抽出したビデオIDをセット
          openOverlay(); // オーバーレイを表示
      }
  };

  // すべてのYouTubeリンクにクリックイベントを登録
  youtubeLinks.forEach(link => {
      link.addEventListener('click', handleLinkClick);
  });

  /**
   * オーバーレイを表示する関数
   * @function openOverlay
   * @description オーバーレイを表示し、プレイヤーを生成します。
   */
  const openOverlay = () => {
      overlay.style.display = 'flex'; // オーバーレイを表示
      setTimeout(() => {
          overlay.classList.add('active'); // オーバーレイにアクティブクラスを追加
          adjustVideoContainerSize(); // ビデオコンテナのサイズを調整
          createOrResetPlayer(); // プレイヤーを作成またはリセット
      }, 10);
  };

  /**
   * オーバーレイを閉じる関数
   * @function closeOverlay
   * @description オーバーレイを非表示にし、プレイヤーを破棄します。
   */
  const closeOverlay = () => {
      overlay.classList.add('passive'); // オーバーレイにパッシブクラスを追加
      setTimeout(() => {
        overlay.style.display = 'none'; // オーバーレイを非表示
        if (player && typeof player.destroy === 'function') {
          player.destroy(); // プレイヤーを破棄
          player = null; // プレイヤーをリセット
        }
        removePlayIcon(); // 再生アイコンを削除
        overlay.classList.remove('active'); // オーバーレイのアクティブクラスを削除
        overlay.classList.remove('passive'); // オーバーレイのパッシブクラスを削除
      }, 300);
  };

  /**
   * オーバーレイがクリックされたときの処理
   * @function handleOverlayClick
   * @param {Event} e - クリックイベント
   * @description オーバーレイの外部クリックでオーバーレイを閉じる処理を行います。
   */
  const handleOverlayClick = (e) => {
      if (e.target === overlay) {
          closeOverlay(); // オーバーレイがクリックされたら閉じる
      }
  };

  // オーバーレイクリック時のイベントリスナーを設定
  overlay.addEventListener('click', handleOverlayClick);

  /**
   * YouTubeリンクからビデオIDを抽出する関数
   * @function extractVideoId
   * @param {string} url - YouTubeのURL
   * @returns {string|null} ビデオID、取得できない場合はnull
   * @description YouTubeのURLからビデオIDを抽出します。
   */
  const extractVideoId = (url) => {
      const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
      const match = url.match(regExp);
      return (match && match[2].length === 11) ? match[2] : null; // ビデオIDを抽出
  };

  /**
   * YouTubeプレイヤーを生成またはリセットする関数
   * @function createOrResetPlayer
   * @description 既存のプレイヤーをリセットし、新しいプレイヤーを生成します。
   */
  const createOrResetPlayer = () => {
      if (player) {
          player.destroy(); // 既存のプレイヤーがあれば破棄
      }
      // 新しいYouTubeプレイヤーを生成
      player = new YT.Player('player', {
          videoId: currentVideoId, // 現在のビデオIDを設定
          playerVars: {
              autoplay: isMobile ? 0 : 1, // モバイルでは自動再生を無効化
              modestbranding: 1,
              rel: 0,
              playsinline: 1,
              controls: 1
          },
          events: {
              'onReady': onPlayerReady // プレイヤー準備完了時のイベント
          }
      });
  };

  /**
   * プレイヤー準備完了時の処理
   * @function onPlayerReady
   * @param {Object} event - YouTubeプレイヤーイベント
   * @description プレイヤーが準備完了した際に、動画の再生やサイズの調整を行います。
   */
  const onPlayerReady = (event) => {
      if (isMobile) {
          addPlayIcon(); // モバイルの場合は再生アイコンを表示
          videoWrapper.addEventListener('click', handleVideoWrapperClick); // 再生アイコンをクリックで再生
      } else {
          event.target.playVideo(); // デスクトップでは自動再生
      }
      adjustVideoContainerSize(); // ビデオコンテナのサイズを再調整
  };

  /**
   * 動画ラッパークリック時の処理
   * @function handleVideoWrapperClick
   * @description モバイルで再生アイコンをクリックした際に動画を再生します。
   */
  const handleVideoWrapperClick = () => {
      if (player && typeof player.playVideo === 'function') {
          player.playVideo(); // 動画を再生
          removePlayIcon(); // 再生アイコンを削除
          videoWrapper.removeEventListener('click', handleVideoWrapperClick); // クリックイベントを解除
      }
  };

  /**
   * 再生アイコンを追加する関数
   * @function addPlayIcon
   * @description モバイル端末で再生アイコンを動画ラッパー内に表示します。
   */
  const addPlayIcon = () => {
      const playIcon = document.createElement('div');
      playIcon.className = 'play-icon'; // 再生アイコンにクラスを付与
      playIcon.innerHTML = ``;
      videoWrapper.appendChild(playIcon); // 動画ラッパーに再生アイコンを追加
  };

  /**
   * 再生アイコンを削除する関数
   * @function removePlayIcon
   * @description 再生アイコンを動画ラッパーから削除します。
   */
  const removePlayIcon = () => {
      const playIcon = videoWrapper.querySelector('.play-icon');
      if (playIcon) {
          playIcon.remove(); // 再生アイコンを削除
      }
  };

  /**
   * ビデオコンテナのサイズを調整する関数
   * @function adjustVideoContainerSize
   * @description ビデオコンテナのサイズをウィンドウに合わせて16:9のアスペクト比を維持するように調整します。
   */
  const adjustVideoContainerSize = () => {
      const aspectRatio = 16 / 9; // アスペクト比 16:9
      const windowWidth = window.innerWidth;
      const windowHeight = window.innerHeight;
      const windowRatio = windowWidth / windowHeight;

      let containerWidth, containerHeight;

      // ウィンドウの比率に応じてビデオコンテナのサイズを計算
      if (windowRatio < aspectRatio) {
          containerWidth = Math.min(windowWidth * 0.9, 1600);
          containerHeight = containerWidth / aspectRatio;
      } else {
          containerHeight = Math.min(windowHeight * 0.9, 900);
          containerWidth = containerHeight * aspectRatio;
      }

      // ビデオコンテナのサイズを設定
      videoContainer.style.width = `${containerWidth}px`;
      videoContainer.style.height = `${containerHeight}px`;
  };

  // 初期サイズ設定
  adjustVideoContainerSize();

  // ウィンドウリサイズ時のイベントリスナーを設定
  window.addEventListener('resize', adjustVideoContainerSize);
};

/**
 * YouTube IFrame APIの準備完了時に呼び出される関数
 * @function onYouTubeIframeAPIReady
 * @description YouTube IFrame APIが準備完了したときにプレイヤーを初期化します。
 */
export const onYouTubeIframeAPIReady = () => {
  initYouTubePlayer(); // プレイヤーを初期化
};