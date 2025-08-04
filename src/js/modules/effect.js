/** ================================================================================================
 * セクションのアニメーション効果を設定する
 * @function effectSection
 * @description 各セクションのスクロールアニメーションを設定します
 */
export function effectSection() {
  const startValue = gMatchMedia.matches ? '70%' : '80%';
  // const endValue = gMatchMedia.matches ? '70%' : '80%';
  const endValue = gMatchMedia.matches ? 'top' : 'top';

  gsap.utils.toArray('.l-section').forEach((section) => {
    // const { start = startValue, end = '70%', marker = false } = section.dataset;
    const { start = startValue, end = endValue, marker = false } = section.dataset;
    
    // ScrollTriggerの設定
    ScrollTrigger.create({
      trigger: section,
      start: `top ${start}`,
      end: `bottom ${end}`,
      toggleClass: { targets: section, className: '-inview' },
      markers: marker === 'true',
      // markers: true,
      // onEnter: () => {
      //   ScrollTrigger.refresh();
      // },

    });
    // ScrollTrigger.refresh();
  });
}

/** ================================================================================================
 * アニメーション設定関数
 * @function animate
 * @param {HTMLElement} target - アニメーション対象の要素
 * @param {'in' | 'out'} direction - アニメーションの方向
 * @param {Object} gsapProps - GSAPのプロパティ
 * @param {number} duration - アニメーションの持続時間
 * @param {number} delay - アニメーションの遅延時間
 * @description 要素のアニメーションを設定します。方向に応じて不透明度や位置、スケールを調整します。
 */
const animate = (target, direction, gsapProps, duration, delay) => {
  const animProps = { ...gsapProps };
  if (direction === 'in') {
    animProps.autoAlpha = 1;
    animProps.x = 0;
    animProps.y = 0;
    animProps.scale = animProps.scale || 1;
  } else {
    animProps.autoAlpha = 0;
  }
  gsap.to(target, { ...animProps, duration, delay });
};

/** ================================================================================================
 * スクロール監視の設定を行う
 * @function setObserver
 * @description .-observe クラスを持つ要素に対してスクロールアニメーションを設定します。
 * ScrollTriggerを使用して、要素が画面に入る/出る際のアニメーションを制御します。
 */
export const setObserver = () => {
  // アニメーション設定から除外する属性のリスト
  const excludeObserveAnimationKeys = ['start', 'end', 'marker', 'sp', 'once', 'scrub', 'pin', 'toggleActions', 'pinSpacing', 'id', 'class', 'style', 'ease', 'scroll', 'stagger', 'duration', 'delay', 'repeat', 'repeatDelay', 'yoyo'];

  gsap.utils.toArray('.-observe').forEach((elm) => {
    const gsapProps = Object.fromEntries(
      Object.entries(elm.dataset).filter(([key]) => !excludeObserveAnimationKeys.includes(key))
    );

    const { start = '65%', end = 'top', marker = 'false', once = 'false' } = elm.dataset;
    const duration = parseFloat(elm.dataset.duration) || 1;
    const delay = parseFloat(elm.dataset.delay) || 0;

    // 初期状態を設定
    gsap.set(elm, { autoAlpha: 0, ...gsapProps });

    // ScrollTriggerの設定
    ScrollTrigger.create({
      trigger: elm,
      start: `top ${start}`,
      end: `bottom ${end}`,
      toggleClass: { targets: elm, className: '-in' },
      onEnter: () => animate(elm, 'in', gsapProps, duration, delay),
      onLeave: () => animate(elm, 'out', gsapProps, duration, 0),
      onEnterBack: () => animate(elm, 'in', gsapProps, duration, delay),
      onLeaveBack: () => animate(elm, 'out', gsapProps, duration, 0),
      once: once === 'true',
      markers: marker === 'true',
      // markers: true,
    });
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
  // const containers = document.querySelectorAll(selector);
  
  // containers.forEach((container, index) => {
  gsap.utils.toArray(selector).forEach((container, index) => {
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
      start: "top bottom",
      end: "bottom top",
      toggleActions: "play pause resume reset",
      onEnter: () => {
        // console.log(`Container ${index + 1}: Enter`);
        resetPositionAndAddStartClass();
        // tl.restart();
        // tl.restart();
        tl.play();
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