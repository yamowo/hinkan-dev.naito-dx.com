/** ------------------------------------------------------------
 * 共通のSwiperオプション設定
 * @const commonSwiperOptions
 * @type {Object}
 * @description 全てのSwiperインスタンスに共通するデフォルト設定です。
 * レスポンシブデザイン、自動再生、ループ再生などの基本的な設定が含まれています。
 */
const commonSwiperOptions = { 
  spaceBetween: 0, 
  centeredSlides: true,
  loop: true, 
  loopAdditionalSlides: 2, 
  pagination: false, 
  navigation: false,
  mousewheel: false, 
  speed: 500,
  autoplay: {
    delay: 8000,
    disableOnInteraction: false,  // true: ユーザーが操作したときに自動再生を止める
    waitForTransition: false,     // true: スライド切り替えのアニメーションの間は自動再生を止める。true だと1枚目のスライドだけ表示時間が短くなるため、表示時間を揃えたいなら false にするのがお勧め
  },
  slidesPerView: 'auto',
  breakpoints: {
    600: { slidesPerView: 'auto', centeredSlides: false }
  },
};

/** ------------------------------------------------------------
 * Swiperを設定する関数
 * @const setupSwiper
 * @description ScrollTriggerで指定された要素に対してSwiperを設定し、要素が表示されるとSwiperを初期化します。
 * @param {HTMLElement} elm - Swiperを初期化する対象の要素
 * @param {Object} options - Swiperの設定オプション
 * @param {boolean} [resetOnEnter=false] - 表示領域に入るたびにスライドを最初に戻すかどうかのフラグ
 */
export const setupSwiper = (elm, options, resetOnEnter = false) => {
  gsap.set(elm, { autoAlpha: 0, height: 'auto' });

  ScrollTrigger.create({
    trigger: elm,
    start: 'top bottom',
    onEnter: () => {
      const delay = elm.classList.contains('p-mainVisual__postSlider') ? 7 : 0;
      gsap.delayedCall(delay, () => {
        // initSwiper(elm, options);
        if (!elm.querySelector('.swiper').dataset.swiperInitialized) {
          initSwiper(elm, options);
        } else if (resetOnEnter) {
          elm.querySelector('.swiper').swiper.slideTo(0, 0);
        }
        // ScrollTrigger.refresh();
        gsap.to(elm, { autoAlpha: 1, duration: 1, delay: 0.75 });
      });
    },
    // markers: true,
  });
};


/** ------------------------------------------------------------
 * Swiperインスタンスを初期化する関数
 * @function initSwiper
 * @param {HTMLElement} elm - Swiperを初期化する要素
 * @param {Object} options - Swiperの設定オプション
 * @description 指定された要素に対してSwiperを初期化します。初期化済みの要素は無視します。
 * また、サムネイル要素が存在する場合、ボタンの位置を調整します。
 */
const initSwiper = (elm, options) => {
  const swiperEl = elm.querySelector('.swiper');
  if (!swiperEl || swiperEl.dataset.swiperInitialized) return;

  const swiperInstance = new SwiperCreator(swiperEl, options);
  swiperEl.dataset.swiperInitialized = 'true';

  const elThumb = swiperEl.querySelector('.p-postList__thumb');
  if (elThumb) {
    const elButtons = swiperEl.parentNode.querySelectorAll('button');
    elButtons.forEach(elButton => {
      elButton.style.top = `${elThumb.clientHeight / 1.9}px`;
    });
  }
};

/** ------------------------------------------------------------
 * Swiperインスタンスを作成するクラス
 * @class SwiperCreator
 * @description Swiperのインスタンスを作成し、オプションに基づいてSwiperを初期化します。
 * 共通のオプションと個別のオプションをマージし、必要な要素を動的に追加します。
 */
class SwiperCreator {
  /**
   * @constructor
   * @param {HTMLElement} el - Swiperを初期化する要素
   * @param {Object} options - 個別のSwiperオプション
   */
  constructor(el, options) {
    this.el = el;
    this.options = {
      ...commonSwiperOptions,
      ...options,
    };
    this._init();
  }

  /**
   * Swiperの初期化を行うプライベートメソッド
   * @private
   */
  _init() {
    const swiperElement = this.el;
    const swiperWrapper = swiperElement.firstElementChild;
    if (!swiperWrapper) return;

    swiperWrapper.classList.add('swiper-wrapper');
    Array.from(swiperWrapper.children).forEach(slide => {
      slide.classList.add('swiper-slide');
    });

    const swiperParent = swiperElement.parentNode;
    if (!swiperParent) return;

    swiperParent.classList.add('swiper-parent');

    const slideCount = window.innerWidth >= 600 ? this.options.breakpoints[600].slidesPerView : this.options.slidesPerView;

    if (this.options.navigation || this.options.pagination) {
      const elements = [
        { tag: 'div', className: 'swiper-pagination', shouldCreate: this.options.pagination },
        { tag: 'button', className: 'swiper-button-prev', shouldCreate: this.options.navigation },
        { tag: 'button', className: 'swiper-button-next', shouldCreate: this.options.navigation },
      ];

      elements.forEach(({ tag, className, shouldCreate }) => {
        if (shouldCreate) {
          const element = document.createElement(tag);
          element.className = className;
          swiperParent.appendChild(element);
        }
      });
    }

    const mergedOptions = { ...this.options };

    if (this.options.navigation) {
      mergedOptions.navigation = {
        nextEl: swiperParent.querySelector(".swiper-button-next"),
        prevEl: swiperParent.querySelector(".swiper-button-prev"),
      };
    }

    if (this.options.pagination) {
      mergedOptions.pagination = {
        el: swiperParent.querySelector(".swiper-pagination"),
        clickable: true,
      };
    }

    new Swiper(swiperElement, mergedOptions);
  }
}