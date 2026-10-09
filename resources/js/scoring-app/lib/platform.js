// True when running inside the Android APK, whose on-device server only
// implements the scoring endpoints. main-standalone.js sets the flag after
// its imports have been evaluated, so read it at call time, never at import.
export function isStandaloneApk() {
    return typeof window !== 'undefined' && window.__DC_STANDALONE === true;
}
