/** ================================================================================================
 * ビューポート、ヘッダー、お知らせバーの高さをカスタムプロパティに設定する関数
 * @function setHeights
 * @description ビューポートの高さ、ヘッダーの高さ、お知らせバーの高さを
 * CSSカスタムプロパティに設定します。
 */
export const setHeights = () => {
  const i = document.querySelector(".c-infoBars");
  const h = document.getElementById("header");
  // ビューポートの高さをCSSカスタムプロパティ --vh に設定
  document.documentElement.style.setProperty("--vh", `${window.innerHeight}px`);

  let s = 0, n = 0;
  h && (s = h.offsetHeight || 0);
  // インフォバーの高さをCSSカスタムプロパティ --dev-headerH に設定
  document.documentElement.style.setProperty("--dev-headerH", `${s}px`);
  i && (n = i.offsetHeight || 0);
  // インフォバーの高さをCSSカスタムプロパティ --dev-infoBarH に設定
  document.documentElement.style.setProperty("--dev-infoBarH", `${n}px`);
};

/** ================================================================================================
 * Lenisによるスムーススクロールを初期化する
 */
export const initializeLenis = () => {
  const lenis = new Lenis({ lerp: 0.1 });
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add((time) => lenis.raf(time * 1000));
  // lenisをグローバル変数に格納
  window.lenis = lenis;
};

/** ================================================================================================
 * GSAPの設定
 * @description GSAPの警告を無効化し、ScrollTriggerプラグインを登録します。
 */
export const initializeGSAP = () => {
  gsap.config({ nullTargetWarn: false });
  gsap.registerPlugin(ScrollTrigger);
  ScrollTrigger.config({
    autoRefreshEvents: "visibilitychange,DOMContentLoaded,load"
  });
};

/** ================================================================================================
 * GSAPを使用してカスタムカーソルを初期化する関数
 * @const initCustomCursorWithGSAP
 * @description カスタムカーソルをSwiper領域内で表示させ、マウス移動に追従する動きを実装します。
 * ヘッダー要素の上ではカスタムカーソルを表示しません。
 */
export const initCustomCursorWithGSAP = () => {
  // スマートフォンの場合は処理を行わない
  if (isSmartPhone()) return;

  // カーソル要素を動的に生成
  const cursor = document.createElement('div');
  cursor.className = 'customCursor -mouse';
  cursor.id = 'customCursor';
  
  // カーソル要素を<body>開始タグ直下に挿入
  const firstBodyChild = document.body.firstChild;
  document.body.insertBefore(cursor, firstBodyChild);

  // Swiper領域とヘッダー要素を取得
  const swiperAreas = document.querySelectorAll('.swiper.-customCursor');
  const header = document.querySelector('.l-header'); // ヘッダー要素を取得

  // Swiper領域がない場合は処理を終了
  if (swiperAreas.length === 0) {
    cursor.remove(); // 生成したカーソル要素を削除
    return;
  }

  // カーソルの初期スタイルを設定
  gsap.set(cursor, { xPercent: -50, yPercent: -50, opacity: 0, display: 'none' });

  /** ================================================================================================
   * マウスの位置に合わせてカーソルを移動させる関数
   * @const handleMouseMove
   * @param {MouseEvent} e - マウスイベント
   */
  const handleMouseMove = (e) => {
    let isInSwiperArea = false;
    let isOverHeader = false;

    // ヘッダーの上にマウスがあるかチェック
    const elementAtPoint = document.elementFromPoint(e.clientX, e.clientY);
    isOverHeader = header.contains(elementAtPoint);

    // ヘッダーの上にマウスがない場合のみSwiper領域をチェック
    if (!isOverHeader) {
      swiperAreas.forEach((swiperArea) => {
        const rect = swiperArea.getBoundingClientRect();
        if (e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom) {
          isInSwiperArea = true;
        }
      });
    }

    // Swiper領域内かつヘッダーの上にない場合はカーソルを表示、それ以外は非表示
    if (isInSwiperArea && !isOverHeader) {
      gsap.to(cursor, { display: 'block', opacity: 1, duration: 0.3, ease: 'power3.out', delay: 0.1 });
    } else {
      gsap.to(cursor, { opacity: 0, duration: 0.3, ease: 'power3.out', onComplete: () => {
        gsap.set(cursor, { display: 'none' });
      }});
    }

    // カーソルをマウスの位置に移動
    gsap.to(cursor, {
      duration: 0.2,
      x: e.pageX,
      y: e.pageY,
      ease: 'power3.out',
    });
  };

  // マウス移動イベントリスナーを追加
  document.addEventListener('mousemove', handleMouseMove);

  // クリーンアップ関数を返す（必要に応じて使用）
  return () => {
    document.removeEventListener('mousemove', handleMouseMove);
    cursor.remove();
  };
};

/** ================================================================================================
 * 画面幅が600px以下かどうかを判定する関数
 * @const isSmartPhone
 * @description ウィンドウ幅が600px以下の場合にtrueを返します。
 * @returns {boolean} 600px以下の場合はtrue
 */
export const isSmartPhone = () => window.matchMedia('(max-width: 600px)').matches;

/** ================================================================================================
 * ホームページかどうかを判定する関数
 * @const isHomePage
 * @description 現在のページがホームページである場合にtrueを返します。
 * @returns {boolean} ホームページの場合はtrue
 */
export const isHomePage = () => bodyWrap?.classList.contains('home');

/** ================================================================================================
 * 固定ページかどうかを判定する関数
 * @param {...number} pageIds - 判定したいページIDの配列
 * @returns {boolean} 指定されたIDのいずれかのページである場合はtrue
 * @example isOnePages(123, 456, 789); ページIDが123、456、789のいずれかの場合、trueを返す
 */
export const isPages = (...pageIds) => {
  // const body = document.body;  
  return pageIds.some(id => bodyWrap.classList.contains(`page-id-${id}`));
};

/** ================================================================================================
 * 投稿ページかどうかを判定する関数
 * @returns {boolean} 投稿ページの場合はtrue
 */
export const isSinglePost = () => {
  return bodyWrap.classList.contains('single-post');
};

/** ================================================================================================
 * スクロールポジションをページのトップにリセットする関数
 * @param {number} [delay=200] - スクロールリセットを実行するまでの遅延時間（ミリ秒）
 * @param {boolean} [debug=false] - デバッグモードを有効にするかどうか
 */
export function resetScrollPosition(delay = 200, debug = false) {
  // デバッグモードが有効な場合、ログを出力
  if (debug) console.log('Attempting to reset scroll position');

  // Lenisが利用可能な場合、遅延してスクロールをリセット
  if (window.lenis) {
    setTimeout(() => {
      try {
        // Lenisのスクロールを一時停止
        window.lenis.stop();
        // スクロール位置を0に設定
        window.lenis.setScroll(0);
        // Lenisのスクロールを再開
        window.lenis.start();

        if (debug) console.log('Scroll position has been reset to top using Lenis');
      } catch (error) {
        // エラーが発生した場合、ログを出力し、通常のスクロールにフォールバック
        console.error('Error resetting scroll with Lenis:', error);
        window.scrollTo(0, 0);
      }
    }, delay);
  } else {
    // Lenisが利用できない場合、通常のスクロールを使用
    window.scrollTo(0, 0);
    if (debug) console.log('Scroll position has been reset to top without Lenis');
  }

  // ScrollTriggerが定義されている場合、更新を実行
  if (typeof ScrollTrigger !== 'undefined') {
    // gsap.to(window, { scrollTo: 0, duration: 0 }); // 強制的にトップに戻す処理
    ScrollTrigger.refresh(); // ScrollTriggerの再計算
    if (debug) console.log('ScrollTrigger has been refreshed');
  }
}

/** ================================================================================================
 * マウスホイールスクロールを有効化するためにイベントの伝播を止める関数
 * @const stopWheelPropagation
 * @description 複数のセレクタに対してホイールイベントの伝播を停止します。
 * @param {...string} selectors - 対象となる要素を取得するためのセレクタ文字列（複数指定可能）
 */
export const stopWheelPropagation = (...selectors) => {
  selectors.forEach(selector => {
    const elements = document.querySelectorAll(selector);
    if (elements.length > 0) {
      elements.forEach(element => {
        element.setAttribute('onwheel', 'event.stopPropagation()');
        element.addEventListener('wheel', e => e.stopPropagation());
        // element.classList.add('-customCursor');
      });
    }
  });
};

/** ================================================================================================
 * 記事スライダーの設定を更新する関数
 * @const updatePostSliderConfig
 * @description スライダーのエフェクトやオートプレイの遅延、スライドの順序を設定します。
 * @param {string} [effect='slide'] - スライダーのエフェクト ('slide', 'fade', etc.)
 * @param {number} [initialDelay=0] - オートプレイを再開する際の遅延時間（ミリ秒単位）
 * @param {boolean} [reverse=false] - スライドを逆順にするかどうかのフラグ
 */
export const updatePostSliderConfig = (effect = 'slide', initialDelay = 0, reverse = false) => {
  if (!window.swellPsSwiper) return;
  const swiperContainer = document.querySelector('.p-postSlider__swiper');
  if (!swiperContainer) return;

  if (reverse) {
    reversePostSlides();
  }

  let currentConfig = window.swellPsSwiper.params;
  currentConfig.effect = effect;

  if (effect === 'slide') {
    currentConfig.mousewheel = true;
    // カスタムカーソルをセット
    stopWheelPropagation('.p-postSlider__swiper.swiper');
    swiperContainer.classList.add('-mouse');
    window.swellPsSwiper.destroy(true, true);
    window.swellPsSwiper = new Swiper(swiperContainer, currentConfig);
  }
  
  if (window.swellPsSwiper.autoplay) {
    window.swellPsSwiper.autoplay.stop();
    setTimeout(() => {
      window.swellPsSwiper.autoplay.start();
    }, initialDelay);
  }
};

/** ================================================================================================
 * スプラッシュ画面のローディング処理を行う関数
 * @const handleSplashLoading
 * @description ページの読み込みが完了してから1秒後に、スプラッシュ画面をフェードアウトさせます。
 * フェードアウト後、スプラッシュ要素を非表示にします。
 */
export const handleSplashLoading = () => {
  const splash = document.getElementById('splash');
  if (!splash) return; // スプラッシュ要素が存在しない場合は処理を終了

  const msFadeout = 500; // フェードアウトにかかる時間（ミリ秒）
  const msDelay = 1000; // ロード完了後のディレイ時間（ミリ秒）

  // フェードアウトの設定
  splash.style.transition = `opacity ${msFadeout}ms`;

  // スプラッシュ画面をフェードアウトさせる関数
  const fadeOutSplash = () => {
    splash.style.opacity = '0';
    
    // フェードアウト完了後にスプラッシュ要素を非表示にする
    setTimeout(() => {
      splash.style.display = 'none';
    }, msFadeout);
  };

  // ロード完了後、指定された遅延時間後にフェードアウトを開始する関数
  const startFadeOutWithDelay = () => {
    setTimeout(fadeOutSplash, msDelay);
  };

  // ページの読み込みが完了したら遅延付きでフェードアウトを開始
  if (document.readyState === 'complete') {
    startFadeOutWithDelay();
  } else {
    window.addEventListener('load', startFadeOutWithDelay);
  }
};

/** ================================================================================================
 * p-postList__title要素をdivに変換する関数
 * @function convertTitlesToDiv
 * @description class="p-postList__title"を持つ要素をすべてdivタグに変換します。
 * この関数は、DOMの読み込みが完了した後に実行されます。
 */
export const convertTitlesToDiv = () => {
  const titles = document.querySelectorAll('.p-postList__title');
  
  titles.forEach(function(title) {
    if (title && title instanceof Element && title.tagName.toLowerCase() !== 'div') {
      const div = document.createElement('div');
      div.className = title.className || '';
      div.innerHTML = title.innerHTML || '';

      if (title.parentNode) {
        title.parentNode.replaceChild(div, title);
      }
    }
  });
};

/** ================================================================================================
 * 指定されたセレクタに一致する要素に対して無限ループアニメーションを設定します。
 * アニメーションは要素が画面に入ったときに開始され、画面外に出たときに一時停止します。
 * アニメーション開始時に親要素に'-start'クラスを付与し、停止時に削除します。
 * 子要素にマウスオーバーするとアニメーションが一時停止し、マウスが外れると再開します。
 * 
 * @param {string} selector - アニメーションを適用する親要素のCSSセレクタ
 * @param {number} [durationSeconds=20] - アニメーションの基準所要時間（秒）
 * @param {boolean} [blank=true] - 子要素間に空白を設けるかどうか
 * @returns {void}
 * 
 * @example
 * // '.p-infiniteLoop'クラスを持つ要素に対して、基準時間10秒のアニメーションを設定し、空白なしでループ
 * setInfiniteLoop('.p-infiniteLoop', 10, false);
 */
export const setInfiniteLoop = (selector, durationSeconds = 20, blank = true) => {
  const containers = document.querySelectorAll(selector);
  
  containers.forEach((container, index) => {
    const inner = container.querySelector(':scope > *');
    if (!inner) return;

    const isRightDirection = container.classList.contains('-toRight');
    const containerWidth = container.offsetWidth;
    let innerWidth = inner.scrollWidth;

    // 子要素を複製してシームレスなループを作成（blankがfalseの場合のみ）
    if (!blank) {
      const children = Array.from(inner.children);
      const clonedChildren = children.map(child => child.cloneNode(true));
      clonedChildren.forEach(child => inner.appendChild(child));
      innerWidth = inner.scrollWidth;
    }

    const baseWidth = 1000;
    const animationDistance = blank ? innerWidth + containerWidth : innerWidth;
    const widthRatio = animationDistance / baseWidth;
    const adjustedDuration = durationSeconds * widthRatio;

    const resetPositionAndAddStartClass = () => {
      gsap.set(inner, { 
        x: isRightDirection ? (blank ? -innerWidth : -innerWidth / 2) : (blank ? containerWidth : 0),
        opacity: blank ? 0 : 1
      });
      container.classList.add('-start');
    };

    const removeStartClass = () => {
      container.classList.remove('-start');
    };

    const tl = gsap.timeline({
      paused: true,
      repeat: -1,
      defaults: { ease: "none" }
    });

    if (blank) {
      tl.to(inner, {
        opacity: 1,
        duration: 0.3
      });
    }

    tl.to(inner, {
      x: isRightDirection ? (blank ? containerWidth : 0) : (blank ? -innerWidth : -innerWidth / 2),
      duration: adjustedDuration,
      ease: "none",
      modifiers: {
        x: gsap.utils.unitize(x => {
          let currentX = parseFloat(x);
          if (blank) {
            if (isRightDirection) {
              if (currentX >= containerWidth) {
                currentX = -innerWidth;
              }
            } else {
              if (currentX <= -innerWidth) {
                currentX = containerWidth;
              }
            }
          } else {
            if (isRightDirection) {
              if (currentX >= 0) {
                currentX -= innerWidth / 2;
              }
            } else {
              if (currentX <= -innerWidth / 2) {
                currentX += innerWidth / 2;
              }
            }
          }
          return currentX;
        })
      },
      onUpdate: () => {
        if (blank) {
          const currentX = gsap.getProperty(inner, "x");
          let opacity;
          if (isRightDirection) {
            opacity = currentX >= -innerWidth && currentX <= containerWidth ? 1 : 
                      currentX < -innerWidth ? (currentX + innerWidth) / containerWidth :
                      (containerWidth - currentX) / containerWidth;
          } else {
            opacity = currentX <= containerWidth && currentX >= -innerWidth ? 1 : 
                      currentX > containerWidth ? (containerWidth - currentX) / containerWidth :
                      (currentX + innerWidth) / containerWidth;
          }
          gsap.set(inner, { opacity: Math.max(0, Math.min(1, opacity)) });
        }
      }
    });

    // マウスオーバー時のアニメーション停止
    const childElements = inner.children;
    Array.from(childElements).forEach(child => {
      child.addEventListener('mouseenter', () => {
        tl.pause();
      });
      child.addEventListener('mouseleave', () => {
        tl.resume();
      });
    });

    ScrollTrigger.create({
      trigger: container,
      start: "top 90%",
      end: "bottom top",
      toggleActions: "play pause resume reset",
      onEnter: () => {
        // console.log(`Container ${index + 1}: Enter`);
        resetPositionAndAddStartClass();
        tl.restart();
      },
      onLeave: () => {
        // console.log(`Container ${index + 1}: Leave`);
        removeStartClass();
        tl.pause();
      },
      onEnterBack: () => {
        // console.log(`Container ${index + 1}: Enter Back`);
        container.classList.add('-start');
        tl.resume();
      },
      onLeaveBack: () => {
        // console.log(`Container ${index + 1}: Leave Back`);
        removeStartClass();
        tl.pause();
      },
      // markers: true,
    });
  });
};