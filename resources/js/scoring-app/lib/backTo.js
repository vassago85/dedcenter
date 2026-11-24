// One back step. If the page we want is already behind this one, pop it
// so the native gesture and the on-screen arrow move the same way.
// Otherwise replace the current entry, so a deep link does not stack a
// second copy and trap the next Back in a loop.

export function historyBackAction(previous, resolvedPath) {
    if (typeof previous !== 'string' || previous.length === 0) return 'replace';
    if (typeof resolvedPath !== 'string' || resolvedPath.length === 0) return 'replace';
    return pathOnly(previous) === pathOnly(resolvedPath) ? 'back' : 'replace';
}

export function backTo(router, to) {
    const target = router.resolve(to);
    const previous = typeof window !== 'undefined' ? window.history.state?.back : undefined;
    const action = historyBackAction(previous, target.fullPath || target.path);
    if (action === 'back') router.back();
    else router.replace(to);
}

function pathOnly(path) {
    let end = path.length;
    const query = path.indexOf('?');
    const hash = path.indexOf('#');
    if (query >= 0) end = Math.min(end, query);
    if (hash >= 0) end = Math.min(end, hash);
    if (end > 1 && path.charAt(end - 1) === '/') end -= 1;
    return path.slice(0, end);
}
