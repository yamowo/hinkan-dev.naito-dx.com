/** ------------------------------------------------------------
 * 
 * @file main.js
 * @description このファイルは、ウェブページの初期化処理を行うスクリプトです。
 * 
 */
import * as com from './modules/common.js';
import * as efc from './modules/effect.js';
import { setupSwiper } from './modules/swiperCreator.js';

(() => {
  // スプラッシュ画面を表示
  com.handleSplashLoading();
  // 初期化関数 setHeights を実行
  com.setHeights();

  /** ------------------------------------------------------------
   * 画面の向きが変わった際の処理
   * @description 画面の向きが変わった後、少し遅延を入れてsetHeights関数を再実行します。
   */
  window.addEventListener("orientationchange", () => {
    setTimeout(() => {
      com.setHeights(); // 画面の向きが変わったら再実行
    }, 10);
  });
  
  // // Barba.js のページ遷移後に初期化関数を再実行
  // window.SWELLHOOK && window.SWELLHOOK.barbaAfter.add(com.setHeights);

  // /** ------------------------------------------------------------
  //  * GSAPの設定
  //  * @description GSAPの警告を無効化し、ScrollTriggerプラグインを登録します。
  //  */
  // gsap.config({ nullTargetWarn: false });
  // gsap.registerPlugin(ScrollTrigger);
  // ScrollTrigger.config({
  //   autoRefreshEvents: "visibilitychange,DOMContentLoaded,load"
  // });

  // /** ------------------------------------------------------------
  //  * Lenisの設定
  //  * @description スムーススクロールライブラリLenisを初期化し、GSAPと連携させます。
  //  */
  // const lenis = new Lenis({ lerp: 0.07 });
  // lenis.on("scroll", ScrollTrigger.update);
  // gsap.ticker.add((time) => {
  //   lenis.raf(time * 1000);
  // });

  // // lenisをグローバル変数に格納
  // window.lenis = lenis;

})();


/** ------------------------------------------------------------
 * 全てのSwiperを初期化する関数
 * @const initSwipers
 * @description 指定されたセクション内の要素に対してSwiperを初期化します。
 */
const initSwipers = () => {

  // Swiperを初期化するセクションとオプションのリスト
  const swiperSections = [
    { selector: '.p-information.slide', options: { autoplay: false, mousewheel: true, loop: false, duration: 500,}},
  ];

  swiperSections.forEach(section => {
    const elements = document.querySelectorAll(section.selector);
    elements.forEach(elm => setupSwiper(elm, section.options, true));
  });
};

/** ------------------------------------------------------------
 * DOMContentLoaded イベント時の処理を行う関数
 * @const handleDOMContentLoaded
 * @description ページの初期ロード時に呼び出される関数
 */
const handleDOMContentLoaded = () => {
  // ページの初期ロード時の処理（必要に応じて実装）
  if (com.isHomePage) {
    initSwipers();
    // com.updatePostSliderConfig('slide', 1000, false);
    // com.convertTitlesToDiv();
    // com.stopWheelPropagation('.slide .p-postListWrap.swiper',);
    // com.initCustomCursorWithGSAP();
  }

};

/** ------------------------------------------------------------
 * ページのロード完了時に呼び出される関数
 * @const handleLoad
 * @description ページが完全にロードされた際に呼び出され、要素のスクロール監視を設定します。
 */
const handleLoad = () => {
  if (com.isHomePage) {
    // スクロール監視の設定
    efc.setObserver();
    // セクションの表示クラス.-inview を設定
    efc.effectSection();
    // インフィニットループの設定
    com.setInfiniteLoop('.p-infiniteLoop', 8); // クラス名は任意に変更
    com.setInfiniteLoop('.p-infiniteLoop.test', 10, false); // クラス名は任意に変更
    // com.setInfiniteLoop('.p-infiniteLoop.-toLeft', 6); // クラス名は任意に変更
    // com.setInfiniteLoop('.p-infiniteLoop.-toRight', 10); // クラス名は任意に変更
  }
};

/** ------------------------------------------------------------
 * リサイズイベント時の処理を行う関数
 * @const handleResize
 * @description ウィンドウのリサイズ時に実行される処理を定義します。（必要に応じて実装）
 */
const handleResize = () => {
  // リサイズ時の処理（必要に応じて実装）
};

/** ------------------------------------------------------------
 * イベントリスナーを追加する関数
 * @const addEventListeners
 * @description DOMContentLoaded、load、resize、Barba.jsのページ遷移イベントに対するリスナーを登録します。
 */
addEventListeners = () => {
// document.addEventListener('DOMContentLoaded', handleDOMContentLoaded);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', handleDOMContentLoaded);
} else {
  handleDOMContentLoaded();
}

window.addEventListener('load', handleLoad);
};

addEventListeners();