/**
 * Platform detection utilities
 * Replacement for Onsen UI platform detection APIs
 * 
 * TODO: Enhance these detection methods as needed
 * Previously used: this.$ons.platform.isAndroid(), this.$ons.platform.isIPhoneX(), etc.
 */

export default {
  /**
   * Check if the platform is Android
   * @returns {boolean}
   */
  isAndroid() {
    // TODO: Implement proper Android detection
    return /android/i.test(navigator.userAgent);
  },

  /**
   * Check if the platform is iOS
   * @returns {boolean}
   */
  isIOS() {
    // TODO: Implement proper iOS detection
    return /iPhone|iPad|iPod/i.test(navigator.userAgent);
  },

  /**
   * Check if the device is iPhone X or similar (with notch)
   * @returns {boolean}
   */
  isIPhoneX() {
    // TODO: Implement proper iPhone X detection
    // This is a basic check - may need refinement
    if (!/iPhone/i.test(navigator.userAgent)) {
      return false;
    }
    
    // iPhone X, XS, XS Max, XR, 11, 11 Pro, 11 Pro Max, 12, 12 Pro, 12 Pro Max, 12 mini have specific screen sizes
    const ratio = window.devicePixelRatio || 1;
    const screen = {
      width: window.screen.width * ratio,
      height: window.screen.height * ratio
    };
    
    // Known iPhone X-style device resolutions
    const iPhoneXSizes = [
      { width: 1125, height: 2436 }, // X, XS, 11 Pro
      { width: 828, height: 1792 },  // XR, 11
      { width: 1242, height: 2688 }, // XS Max, 11 Pro Max
      { width: 1170, height: 2532 }, // 12, 12 Pro
      { width: 1284, height: 2778 }, // 12 Pro Max
      { width: 1080, height: 2340 }  // 12 mini
    ];
    
    return iPhoneXSizes.some(size => 
      (screen.width === size.width && screen.height === size.height) ||
      (screen.width === size.height && screen.height === size.width)
    );
  },

  /**
   * Check if running as a web view (Cordova, etc.)
   * @returns {boolean}
   */
  isWebView() {
    // TODO: Implement proper web view detection
    return !!window.cordova;
  }
};
