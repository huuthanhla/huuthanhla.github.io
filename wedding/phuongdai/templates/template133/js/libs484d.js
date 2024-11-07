//**
// * @popperjs/core v2.11.8 - MIT License
// */

!(function (e, t) {
    "object" == typeof exports && "undefined" != typeof module ? t(exports) : "function" == typeof define && define.amd ? define(["exports"], t) : t(((e = "undefined" != typeof globalThis ? globalThis : e || self).Popper = {}));
})(this, function (e) {
    "use strict";
    function t(e) {
        if (null == e) return window;
        if ("[object Window]" !== e.toString()) {
            var t = e.ownerDocument;
            return (t && t.defaultView) || window;
        }
        return e;
    }
    function n(e) {
        return e instanceof t(e).Element || e instanceof Element;
    }
    function r(e) {
        return e instanceof t(e).HTMLElement || e instanceof HTMLElement;
    }
    function o(e) {
        return "undefined" != typeof ShadowRoot && (e instanceof t(e).ShadowRoot || e instanceof ShadowRoot);
    }
    var i = Math.max,
        a = Math.min,
        s = Math.round;
    function f() {
        var e = navigator.userAgentData;
        return null != e && e.brands && Array.isArray(e.brands)
            ? e.brands
                  .map(function (e) {
                      return e.brand + "/" + e.version;
                  })
                  .join(" ")
            : navigator.userAgent;
    }
    function c() {
        return !/^((?!chrome|android).)*safari/i.test(f());
    }
    function p(e, o, i) {
        void 0 === o && (o = !1), void 0 === i && (i = !1);
        var a = e.getBoundingClientRect(),
            f = 1,
            p = 1;
        o && r(e) && ((f = (e.offsetWidth > 0 && s(a.width) / e.offsetWidth) || 1), (p = (e.offsetHeight > 0 && s(a.height) / e.offsetHeight) || 1));
        var u = (n(e) ? t(e) : window).visualViewport,
            l = !c() && i,
            d = (a.left + (l && u ? u.offsetLeft : 0)) / f,
            h = (a.top + (l && u ? u.offsetTop : 0)) / p,
            m = a.width / f,
            v = a.height / p;
        return { width: m, height: v, top: h, right: d + m, bottom: h + v, left: d, x: d, y: h };
    }
    function u(e) {
        var n = t(e);
        return { scrollLeft: n.pageXOffset, scrollTop: n.pageYOffset };
    }
    function l(e) {
        return e ? (e.nodeName || "").toLowerCase() : null;
    }
    function d(e) {
        return ((n(e) ? e.ownerDocument : e.document) || window.document).documentElement;
    }
    function h(e) {
        return p(d(e)).left + u(e).scrollLeft;
    }
    function m(e) {
        return t(e).getComputedStyle(e);
    }
    function v(e) {
        var t = m(e),
            n = t.overflow,
            r = t.overflowX,
            o = t.overflowY;
        return /auto|scroll|overlay|hidden/.test(n + o + r);
    }
    function y(e, n, o) {
        void 0 === o && (o = !1);
        var i,
            a,
            f = r(n),
            c =
                r(n) &&
                (function (e) {
                    var t = e.getBoundingClientRect(),
                        n = s(t.width) / e.offsetWidth || 1,
                        r = s(t.height) / e.offsetHeight || 1;
                    return 1 !== n || 1 !== r;
                })(n),
            m = d(n),
            y = p(e, c, o),
            g = { scrollLeft: 0, scrollTop: 0 },
            b = { x: 0, y: 0 };
        return (
            (f || (!f && !o)) &&
                (("body" !== l(n) || v(m)) && (g = (i = n) !== t(i) && r(i) ? { scrollLeft: (a = i).scrollLeft, scrollTop: a.scrollTop } : u(i)), r(n) ? (((b = p(n, !0)).x += n.clientLeft), (b.y += n.clientTop)) : m && (b.x = h(m))),
            { x: y.left + g.scrollLeft - b.x, y: y.top + g.scrollTop - b.y, width: y.width, height: y.height }
        );
    }
    function g(e) {
        var t = p(e),
            n = e.offsetWidth,
            r = e.offsetHeight;
        return Math.abs(t.width - n) <= 1 && (n = t.width), Math.abs(t.height - r) <= 1 && (r = t.height), { x: e.offsetLeft, y: e.offsetTop, width: n, height: r };
    }
    function b(e) {
        return "html" === l(e) ? e : e.assignedSlot || e.parentNode || (o(e) ? e.host : null) || d(e);
    }
    function x(e) {
        return ["html", "body", "#document"].indexOf(l(e)) >= 0 ? e.ownerDocument.body : r(e) && v(e) ? e : x(b(e));
    }
    function w(e, n) {
        var r;
        void 0 === n && (n = []);
        var o = x(e),
            i = o === (null == (r = e.ownerDocument) ? void 0 : r.body),
            a = t(o),
            s = i ? [a].concat(a.visualViewport || [], v(o) ? o : []) : o,
            f = n.concat(s);
        return i ? f : f.concat(w(b(s)));
    }
    function O(e) {
        return ["table", "td", "th"].indexOf(l(e)) >= 0;
    }
    function j(e) {
        return r(e) && "fixed" !== m(e).position ? e.offsetParent : null;
    }
    function E(e) {
        for (var n = t(e), i = j(e); i && O(i) && "static" === m(i).position; ) i = j(i);
        return i && ("html" === l(i) || ("body" === l(i) && "static" === m(i).position))
            ? n
            : i ||
                  (function (e) {
                      var t = /firefox/i.test(f());
                      if (/Trident/i.test(f()) && r(e) && "fixed" === m(e).position) return null;
                      var n = b(e);
                      for (o(n) && (n = n.host); r(n) && ["html", "body"].indexOf(l(n)) < 0; ) {
                          var i = m(n);
                          if (
                              "none" !== i.transform ||
                              "none" !== i.perspective ||
                              "paint" === i.contain ||
                              -1 !== ["transform", "perspective"].indexOf(i.willChange) ||
                              (t && "filter" === i.willChange) ||
                              (t && i.filter && "none" !== i.filter)
                          )
                              return n;
                          n = n.parentNode;
                      }
                      return null;
                  })(e) ||
                  n;
    }
    var D = "top",
        A = "bottom",
        L = "right",
        P = "left",
        M = "auto",
        k = [D, A, L, P],
        W = "start",
        B = "end",
        H = "viewport",
        T = "popper",
        R = k.reduce(function (e, t) {
            return e.concat([t + "-" + W, t + "-" + B]);
        }, []),
        S = [].concat(k, [M]).reduce(function (e, t) {
            return e.concat([t, t + "-" + W, t + "-" + B]);
        }, []),
        V = ["beforeRead", "read", "afterRead", "beforeMain", "main", "afterMain", "beforeWrite", "write", "afterWrite"];
    function q(e) {
        var t = new Map(),
            n = new Set(),
            r = [];
        function o(e) {
            n.add(e.name),
                [].concat(e.requires || [], e.requiresIfExists || []).forEach(function (e) {
                    if (!n.has(e)) {
                        var r = t.get(e);
                        r && o(r);
                    }
                }),
                r.push(e);
        }
        return (
            e.forEach(function (e) {
                t.set(e.name, e);
            }),
            e.forEach(function (e) {
                n.has(e.name) || o(e);
            }),
            r
        );
    }
    function C(e, t) {
        var n = t.getRootNode && t.getRootNode();
        if (e.contains(t)) return !0;
        if (n && o(n)) {
            var r = t;
            do {
                if (r && e.isSameNode(r)) return !0;
                r = r.parentNode || r.host;
            } while (r);
        }
        return !1;
    }
    function N(e) {
        return Object.assign({}, e, { left: e.x, top: e.y, right: e.x + e.width, bottom: e.y + e.height });
    }
    function I(e, r, o) {
        return r === H
            ? N(
                  (function (e, n) {
                      var r = t(e),
                          o = d(e),
                          i = r.visualViewport,
                          a = o.clientWidth,
                          s = o.clientHeight,
                          f = 0,
                          p = 0;
                      if (i) {
                          (a = i.width), (s = i.height);
                          var u = c();
                          (u || (!u && "fixed" === n)) && ((f = i.offsetLeft), (p = i.offsetTop));
                      }
                      return { width: a, height: s, x: f + h(e), y: p };
                  })(e, o)
              )
            : n(r)
            ? (function (e, t) {
                  var n = p(e, !1, "fixed" === t);
                  return (
                      (n.top = n.top + e.clientTop),
                      (n.left = n.left + e.clientLeft),
                      (n.bottom = n.top + e.clientHeight),
                      (n.right = n.left + e.clientWidth),
                      (n.width = e.clientWidth),
                      (n.height = e.clientHeight),
                      (n.x = n.left),
                      (n.y = n.top),
                      n
                  );
              })(r, o)
            : N(
                  (function (e) {
                      var t,
                          n = d(e),
                          r = u(e),
                          o = null == (t = e.ownerDocument) ? void 0 : t.body,
                          a = i(n.scrollWidth, n.clientWidth, o ? o.scrollWidth : 0, o ? o.clientWidth : 0),
                          s = i(n.scrollHeight, n.clientHeight, o ? o.scrollHeight : 0, o ? o.clientHeight : 0),
                          f = -r.scrollLeft + h(e),
                          c = -r.scrollTop;
                      return "rtl" === m(o || n).direction && (f += i(n.clientWidth, o ? o.clientWidth : 0) - a), { width: a, height: s, x: f, y: c };
                  })(d(e))
              );
    }
    function _(e, t, o, s) {
        var f =
                "clippingParents" === t
                    ? (function (e) {
                          var t = w(b(e)),
                              o = ["absolute", "fixed"].indexOf(m(e).position) >= 0 && r(e) ? E(e) : e;
                          return n(o)
                              ? t.filter(function (e) {
                                    return n(e) && C(e, o) && "body" !== l(e);
                                })
                              : [];
                      })(e)
                    : [].concat(t),
            c = [].concat(f, [o]),
            p = c[0],
            u = c.reduce(function (t, n) {
                var r = I(e, n, s);
                return (t.top = i(r.top, t.top)), (t.right = a(r.right, t.right)), (t.bottom = a(r.bottom, t.bottom)), (t.left = i(r.left, t.left)), t;
            }, I(e, p, s));
        return (u.width = u.right - u.left), (u.height = u.bottom - u.top), (u.x = u.left), (u.y = u.top), u;
    }
    function F(e) {
        return e.split("-")[0];
    }
    function U(e) {
        return e.split("-")[1];
    }
    function z(e) {
        return ["top", "bottom"].indexOf(e) >= 0 ? "x" : "y";
    }
    function X(e) {
        var t,
            n = e.reference,
            r = e.element,
            o = e.placement,
            i = o ? F(o) : null,
            a = o ? U(o) : null,
            s = n.x + n.width / 2 - r.width / 2,
            f = n.y + n.height / 2 - r.height / 2;
        switch (i) {
            case D:
                t = { x: s, y: n.y - r.height };
                break;
            case A:
                t = { x: s, y: n.y + n.height };
                break;
            case L:
                t = { x: n.x + n.width, y: f };
                break;
            case P:
                t = { x: n.x - r.width, y: f };
                break;
            default:
                t = { x: n.x, y: n.y };
        }
        var c = i ? z(i) : null;
        if (null != c) {
            var p = "y" === c ? "height" : "width";
            switch (a) {
                case W:
                    t[c] = t[c] - (n[p] / 2 - r[p] / 2);
                    break;
                case B:
                    t[c] = t[c] + (n[p] / 2 - r[p] / 2);
            }
        }
        return t;
    }
    function Y(e) {
        return Object.assign({}, { top: 0, right: 0, bottom: 0, left: 0 }, e);
    }
    function G(e, t) {
        return t.reduce(function (t, n) {
            return (t[n] = e), t;
        }, {});
    }
    function J(e, t) {
        void 0 === t && (t = {});
        var r = t,
            o = r.placement,
            i = void 0 === o ? e.placement : o,
            a = r.strategy,
            s = void 0 === a ? e.strategy : a,
            f = r.boundary,
            c = void 0 === f ? "clippingParents" : f,
            u = r.rootBoundary,
            l = void 0 === u ? H : u,
            h = r.elementContext,
            m = void 0 === h ? T : h,
            v = r.altBoundary,
            y = void 0 !== v && v,
            g = r.padding,
            b = void 0 === g ? 0 : g,
            x = Y("number" != typeof b ? b : G(b, k)),
            w = m === T ? "reference" : T,
            O = e.rects.popper,
            j = e.elements[y ? w : m],
            E = _(n(j) ? j : j.contextElement || d(e.elements.popper), c, l, s),
            P = p(e.elements.reference),
            M = X({ reference: P, element: O, strategy: "absolute", placement: i }),
            W = N(Object.assign({}, O, M)),
            B = m === T ? W : P,
            R = { top: E.top - B.top + x.top, bottom: B.bottom - E.bottom + x.bottom, left: E.left - B.left + x.left, right: B.right - E.right + x.right },
            S = e.modifiersData.offset;
        if (m === T && S) {
            var V = S[i];
            Object.keys(R).forEach(function (e) {
                var t = [L, A].indexOf(e) >= 0 ? 1 : -1,
                    n = [D, A].indexOf(e) >= 0 ? "y" : "x";
                R[e] += V[n] * t;
            });
        }
        return R;
    }
    var K = { placement: "bottom", modifiers: [], strategy: "absolute" };
    function Q() {
        for (var e = arguments.length, t = new Array(e), n = 0; n < e; n++) t[n] = arguments[n];
        return !t.some(function (e) {
            return !(e && "function" == typeof e.getBoundingClientRect);
        });
    }
    function Z(e) {
        void 0 === e && (e = {});
        var t = e,
            r = t.defaultModifiers,
            o = void 0 === r ? [] : r,
            i = t.defaultOptions,
            a = void 0 === i ? K : i;
        return function (e, t, r) {
            void 0 === r && (r = a);
            var i,
                s,
                f = { placement: "bottom", orderedModifiers: [], options: Object.assign({}, K, a), modifiersData: {}, elements: { reference: e, popper: t }, attributes: {}, styles: {} },
                c = [],
                p = !1,
                u = {
                    state: f,
                    setOptions: function (r) {
                        var i = "function" == typeof r ? r(f.options) : r;
                        l(), (f.options = Object.assign({}, a, f.options, i)), (f.scrollParents = { reference: n(e) ? w(e) : e.contextElement ? w(e.contextElement) : [], popper: w(t) });
                        var s,
                            p,
                            d = (function (e) {
                                var t = q(e);
                                return V.reduce(function (e, n) {
                                    return e.concat(
                                        t.filter(function (e) {
                                            return e.phase === n;
                                        })
                                    );
                                }, []);
                            })(
                                ((s = [].concat(o, f.options.modifiers)),
                                (p = s.reduce(function (e, t) {
                                    var n = e[t.name];
                                    return (e[t.name] = n ? Object.assign({}, n, t, { options: Object.assign({}, n.options, t.options), data: Object.assign({}, n.data, t.data) }) : t), e;
                                }, {})),
                                Object.keys(p).map(function (e) {
                                    return p[e];
                                }))
                            );
                        return (
                            (f.orderedModifiers = d.filter(function (e) {
                                return e.enabled;
                            })),
                            f.orderedModifiers.forEach(function (e) {
                                var t = e.name,
                                    n = e.options,
                                    r = void 0 === n ? {} : n,
                                    o = e.effect;
                                if ("function" == typeof o) {
                                    var i = o({ state: f, name: t, instance: u, options: r }),
                                        a = function () {};
                                    c.push(i || a);
                                }
                            }),
                            u.update()
                        );
                    },
                    forceUpdate: function () {
                        if (!p) {
                            var e = f.elements,
                                t = e.reference,
                                n = e.popper;
                            if (Q(t, n)) {
                                (f.rects = { reference: y(t, E(n), "fixed" === f.options.strategy), popper: g(n) }),
                                    (f.reset = !1),
                                    (f.placement = f.options.placement),
                                    f.orderedModifiers.forEach(function (e) {
                                        return (f.modifiersData[e.name] = Object.assign({}, e.data));
                                    });
                                for (var r = 0; r < f.orderedModifiers.length; r++)
                                    if (!0 !== f.reset) {
                                        var o = f.orderedModifiers[r],
                                            i = o.fn,
                                            a = o.options,
                                            s = void 0 === a ? {} : a,
                                            c = o.name;
                                        "function" == typeof i && (f = i({ state: f, options: s, name: c, instance: u }) || f);
                                    } else (f.reset = !1), (r = -1);
                            }
                        }
                    },
                    update:
                        ((i = function () {
                            return new Promise(function (e) {
                                u.forceUpdate(), e(f);
                            });
                        }),
                        function () {
                            return (
                                s ||
                                    (s = new Promise(function (e) {
                                        Promise.resolve().then(function () {
                                            (s = void 0), e(i());
                                        });
                                    })),
                                s
                            );
                        }),
                    destroy: function () {
                        l(), (p = !0);
                    },
                };
            if (!Q(e, t)) return u;
            function l() {
                c.forEach(function (e) {
                    return e();
                }),
                    (c = []);
            }
            return (
                u.setOptions(r).then(function (e) {
                    !p && r.onFirstUpdate && r.onFirstUpdate(e);
                }),
                u
            );
        };
    }
    var $ = { passive: !0 };
    var ee = {
        name: "eventListeners",
        enabled: !0,
        phase: "write",
        fn: function () {},
        effect: function (e) {
            var n = e.state,
                r = e.instance,
                o = e.options,
                i = o.scroll,
                a = void 0 === i || i,
                s = o.resize,
                f = void 0 === s || s,
                c = t(n.elements.popper),
                p = [].concat(n.scrollParents.reference, n.scrollParents.popper);
            return (
                a &&
                    p.forEach(function (e) {
                        e.addEventListener("scroll", r.update, $);
                    }),
                f && c.addEventListener("resize", r.update, $),
                function () {
                    a &&
                        p.forEach(function (e) {
                            e.removeEventListener("scroll", r.update, $);
                        }),
                        f && c.removeEventListener("resize", r.update, $);
                }
            );
        },
        data: {},
    };
    var te = {
            name: "popperOffsets",
            enabled: !0,
            phase: "read",
            fn: function (e) {
                var t = e.state,
                    n = e.name;
                t.modifiersData[n] = X({ reference: t.rects.reference, element: t.rects.popper, strategy: "absolute", placement: t.placement });
            },
            data: {},
        },
        ne = { top: "auto", right: "auto", bottom: "auto", left: "auto" };
    function re(e) {
        var n,
            r = e.popper,
            o = e.popperRect,
            i = e.placement,
            a = e.variation,
            f = e.offsets,
            c = e.position,
            p = e.gpuAcceleration,
            u = e.adaptive,
            l = e.roundOffsets,
            h = e.isFixed,
            v = f.x,
            y = void 0 === v ? 0 : v,
            g = f.y,
            b = void 0 === g ? 0 : g,
            x = "function" == typeof l ? l({ x: y, y: b }) : { x: y, y: b };
        (y = x.x), (b = x.y);
        var w = f.hasOwnProperty("x"),
            O = f.hasOwnProperty("y"),
            j = P,
            M = D,
            k = window;
        if (u) {
            var W = E(r),
                H = "clientHeight",
                T = "clientWidth";
            if ((W === t(r) && "static" !== m((W = d(r))).position && "absolute" === c && ((H = "scrollHeight"), (T = "scrollWidth")), (W = W), i === D || ((i === P || i === L) && a === B)))
                (M = A), (b -= (h && W === k && k.visualViewport ? k.visualViewport.height : W[H]) - o.height), (b *= p ? 1 : -1);
            if (i === P || ((i === D || i === A) && a === B)) (j = L), (y -= (h && W === k && k.visualViewport ? k.visualViewport.width : W[T]) - o.width), (y *= p ? 1 : -1);
        }
        var R,
            S = Object.assign({ position: c }, u && ne),
            V =
                !0 === l
                    ? (function (e, t) {
                          var n = e.x,
                              r = e.y,
                              o = t.devicePixelRatio || 1;
                          return { x: s(n * o) / o || 0, y: s(r * o) / o || 0 };
                      })({ x: y, y: b }, t(r))
                    : { x: y, y: b };
        return (
            (y = V.x),
            (b = V.y),
            p
                ? Object.assign({}, S, (((R = {})[M] = O ? "0" : ""), (R[j] = w ? "0" : ""), (R.transform = (k.devicePixelRatio || 1) <= 1 ? "translate(" + y + "px, " + b + "px)" : "translate3d(" + y + "px, " + b + "px, 0)"), R))
                : Object.assign({}, S, (((n = {})[M] = O ? b + "px" : ""), (n[j] = w ? y + "px" : ""), (n.transform = ""), n))
        );
    }
    var oe = {
        name: "computeStyles",
        enabled: !0,
        phase: "beforeWrite",
        fn: function (e) {
            var t = e.state,
                n = e.options,
                r = n.gpuAcceleration,
                o = void 0 === r || r,
                i = n.adaptive,
                a = void 0 === i || i,
                s = n.roundOffsets,
                f = void 0 === s || s,
                c = { placement: F(t.placement), variation: U(t.placement), popper: t.elements.popper, popperRect: t.rects.popper, gpuAcceleration: o, isFixed: "fixed" === t.options.strategy };
            null != t.modifiersData.popperOffsets && (t.styles.popper = Object.assign({}, t.styles.popper, re(Object.assign({}, c, { offsets: t.modifiersData.popperOffsets, position: t.options.strategy, adaptive: a, roundOffsets: f })))),
                null != t.modifiersData.arrow && (t.styles.arrow = Object.assign({}, t.styles.arrow, re(Object.assign({}, c, { offsets: t.modifiersData.arrow, position: "absolute", adaptive: !1, roundOffsets: f })))),
                (t.attributes.popper = Object.assign({}, t.attributes.popper, { "data-popper-placement": t.placement }));
        },
        data: {},
    };
    var ie = {
        name: "applyStyles",
        enabled: !0,
        phase: "write",
        fn: function (e) {
            var t = e.state;
            Object.keys(t.elements).forEach(function (e) {
                var n = t.styles[e] || {},
                    o = t.attributes[e] || {},
                    i = t.elements[e];
                r(i) &&
                    l(i) &&
                    (Object.assign(i.style, n),
                    Object.keys(o).forEach(function (e) {
                        var t = o[e];
                        !1 === t ? i.removeAttribute(e) : i.setAttribute(e, !0 === t ? "" : t);
                    }));
            });
        },
        effect: function (e) {
            var t = e.state,
                n = { popper: { position: t.options.strategy, left: "0", top: "0", margin: "0" }, arrow: { position: "absolute" }, reference: {} };
            return (
                Object.assign(t.elements.popper.style, n.popper),
                (t.styles = n),
                t.elements.arrow && Object.assign(t.elements.arrow.style, n.arrow),
                function () {
                    Object.keys(t.elements).forEach(function (e) {
                        var o = t.elements[e],
                            i = t.attributes[e] || {},
                            a = Object.keys(t.styles.hasOwnProperty(e) ? t.styles[e] : n[e]).reduce(function (e, t) {
                                return (e[t] = ""), e;
                            }, {});
                        r(o) &&
                            l(o) &&
                            (Object.assign(o.style, a),
                            Object.keys(i).forEach(function (e) {
                                o.removeAttribute(e);
                            }));
                    });
                }
            );
        },
        requires: ["computeStyles"],
    };
    var ae = {
            name: "offset",
            enabled: !0,
            phase: "main",
            requires: ["popperOffsets"],
            fn: function (e) {
                var t = e.state,
                    n = e.options,
                    r = e.name,
                    o = n.offset,
                    i = void 0 === o ? [0, 0] : o,
                    a = S.reduce(function (e, n) {
                        return (
                            (e[n] = (function (e, t, n) {
                                var r = F(e),
                                    o = [P, D].indexOf(r) >= 0 ? -1 : 1,
                                    i = "function" == typeof n ? n(Object.assign({}, t, { placement: e })) : n,
                                    a = i[0],
                                    s = i[1];
                                return (a = a || 0), (s = (s || 0) * o), [P, L].indexOf(r) >= 0 ? { x: s, y: a } : { x: a, y: s };
                            })(n, t.rects, i)),
                            e
                        );
                    }, {}),
                    s = a[t.placement],
                    f = s.x,
                    c = s.y;
                null != t.modifiersData.popperOffsets && ((t.modifiersData.popperOffsets.x += f), (t.modifiersData.popperOffsets.y += c)), (t.modifiersData[r] = a);
            },
        },
        se = { left: "right", right: "left", bottom: "top", top: "bottom" };
    function fe(e) {
        return e.replace(/left|right|bottom|top/g, function (e) {
            return se[e];
        });
    }
    var ce = { start: "end", end: "start" };
    function pe(e) {
        return e.replace(/start|end/g, function (e) {
            return ce[e];
        });
    }
    function ue(e, t) {
        void 0 === t && (t = {});
        var n = t,
            r = n.placement,
            o = n.boundary,
            i = n.rootBoundary,
            a = n.padding,
            s = n.flipVariations,
            f = n.allowedAutoPlacements,
            c = void 0 === f ? S : f,
            p = U(r),
            u = p
                ? s
                    ? R
                    : R.filter(function (e) {
                          return U(e) === p;
                      })
                : k,
            l = u.filter(function (e) {
                return c.indexOf(e) >= 0;
            });
        0 === l.length && (l = u);
        var d = l.reduce(function (t, n) {
            return (t[n] = J(e, { placement: n, boundary: o, rootBoundary: i, padding: a })[F(n)]), t;
        }, {});
        return Object.keys(d).sort(function (e, t) {
            return d[e] - d[t];
        });
    }
    var le = {
        name: "flip",
        enabled: !0,
        phase: "main",
        fn: function (e) {
            var t = e.state,
                n = e.options,
                r = e.name;
            if (!t.modifiersData[r]._skip) {
                for (
                    var o = n.mainAxis,
                        i = void 0 === o || o,
                        a = n.altAxis,
                        s = void 0 === a || a,
                        f = n.fallbackPlacements,
                        c = n.padding,
                        p = n.boundary,
                        u = n.rootBoundary,
                        l = n.altBoundary,
                        d = n.flipVariations,
                        h = void 0 === d || d,
                        m = n.allowedAutoPlacements,
                        v = t.options.placement,
                        y = F(v),
                        g =
                            f ||
                            (y === v || !h
                                ? [fe(v)]
                                : (function (e) {
                                      if (F(e) === M) return [];
                                      var t = fe(e);
                                      return [pe(e), t, pe(t)];
                                  })(v)),
                        b = [v].concat(g).reduce(function (e, n) {
                            return e.concat(F(n) === M ? ue(t, { placement: n, boundary: p, rootBoundary: u, padding: c, flipVariations: h, allowedAutoPlacements: m }) : n);
                        }, []),
                        x = t.rects.reference,
                        w = t.rects.popper,
                        O = new Map(),
                        j = !0,
                        E = b[0],
                        k = 0;
                    k < b.length;
                    k++
                ) {
                    var B = b[k],
                        H = F(B),
                        T = U(B) === W,
                        R = [D, A].indexOf(H) >= 0,
                        S = R ? "width" : "height",
                        V = J(t, { placement: B, boundary: p, rootBoundary: u, altBoundary: l, padding: c }),
                        q = R ? (T ? L : P) : T ? A : D;
                    x[S] > w[S] && (q = fe(q));
                    var C = fe(q),
                        N = [];
                    if (
                        (i && N.push(V[H] <= 0),
                        s && N.push(V[q] <= 0, V[C] <= 0),
                        N.every(function (e) {
                            return e;
                        }))
                    ) {
                        (E = B), (j = !1);
                        break;
                    }
                    O.set(B, N);
                }
                if (j)
                    for (
                        var I = function (e) {
                                var t = b.find(function (t) {
                                    var n = O.get(t);
                                    if (n)
                                        return n.slice(0, e).every(function (e) {
                                            return e;
                                        });
                                });
                                if (t) return (E = t), "break";
                            },
                            _ = h ? 3 : 1;
                        _ > 0;
                        _--
                    ) {
                        if ("break" === I(_)) break;
                    }
                t.placement !== E && ((t.modifiersData[r]._skip = !0), (t.placement = E), (t.reset = !0));
            }
        },
        requiresIfExists: ["offset"],
        data: { _skip: !1 },
    };
    function de(e, t, n) {
        return i(e, a(t, n));
    }
    var he = {
        name: "preventOverflow",
        enabled: !0,
        phase: "main",
        fn: function (e) {
            var t = e.state,
                n = e.options,
                r = e.name,
                o = n.mainAxis,
                s = void 0 === o || o,
                f = n.altAxis,
                c = void 0 !== f && f,
                p = n.boundary,
                u = n.rootBoundary,
                l = n.altBoundary,
                d = n.padding,
                h = n.tether,
                m = void 0 === h || h,
                v = n.tetherOffset,
                y = void 0 === v ? 0 : v,
                b = J(t, { boundary: p, rootBoundary: u, padding: d, altBoundary: l }),
                x = F(t.placement),
                w = U(t.placement),
                O = !w,
                j = z(x),
                M = "x" === j ? "y" : "x",
                k = t.modifiersData.popperOffsets,
                B = t.rects.reference,
                H = t.rects.popper,
                T = "function" == typeof y ? y(Object.assign({}, t.rects, { placement: t.placement })) : y,
                R = "number" == typeof T ? { mainAxis: T, altAxis: T } : Object.assign({ mainAxis: 0, altAxis: 0 }, T),
                S = t.modifiersData.offset ? t.modifiersData.offset[t.placement] : null,
                V = { x: 0, y: 0 };
            if (k) {
                if (s) {
                    var q,
                        C = "y" === j ? D : P,
                        N = "y" === j ? A : L,
                        I = "y" === j ? "height" : "width",
                        _ = k[j],
                        X = _ + b[C],
                        Y = _ - b[N],
                        G = m ? -H[I] / 2 : 0,
                        K = w === W ? B[I] : H[I],
                        Q = w === W ? -H[I] : -B[I],
                        Z = t.elements.arrow,
                        $ = m && Z ? g(Z) : { width: 0, height: 0 },
                        ee = t.modifiersData["arrow#persistent"] ? t.modifiersData["arrow#persistent"].padding : { top: 0, right: 0, bottom: 0, left: 0 },
                        te = ee[C],
                        ne = ee[N],
                        re = de(0, B[I], $[I]),
                        oe = O ? B[I] / 2 - G - re - te - R.mainAxis : K - re - te - R.mainAxis,
                        ie = O ? -B[I] / 2 + G + re + ne + R.mainAxis : Q + re + ne + R.mainAxis,
                        ae = t.elements.arrow && E(t.elements.arrow),
                        se = ae ? ("y" === j ? ae.clientTop || 0 : ae.clientLeft || 0) : 0,
                        fe = null != (q = null == S ? void 0 : S[j]) ? q : 0,
                        ce = _ + ie - fe,
                        pe = de(m ? a(X, _ + oe - fe - se) : X, _, m ? i(Y, ce) : Y);
                    (k[j] = pe), (V[j] = pe - _);
                }
                if (c) {
                    var ue,
                        le = "x" === j ? D : P,
                        he = "x" === j ? A : L,
                        me = k[M],
                        ve = "y" === M ? "height" : "width",
                        ye = me + b[le],
                        ge = me - b[he],
                        be = -1 !== [D, P].indexOf(x),
                        xe = null != (ue = null == S ? void 0 : S[M]) ? ue : 0,
                        we = be ? ye : me - B[ve] - H[ve] - xe + R.altAxis,
                        Oe = be ? me + B[ve] + H[ve] - xe - R.altAxis : ge,
                        je =
                            m && be
                                ? (function (e, t, n) {
                                      var r = de(e, t, n);
                                      return r > n ? n : r;
                                  })(we, me, Oe)
                                : de(m ? we : ye, me, m ? Oe : ge);
                    (k[M] = je), (V[M] = je - me);
                }
                t.modifiersData[r] = V;
            }
        },
        requiresIfExists: ["offset"],
    };
    var me = {
        name: "arrow",
        enabled: !0,
        phase: "main",
        fn: function (e) {
            var t,
                n = e.state,
                r = e.name,
                o = e.options,
                i = n.elements.arrow,
                a = n.modifiersData.popperOffsets,
                s = F(n.placement),
                f = z(s),
                c = [P, L].indexOf(s) >= 0 ? "height" : "width";
            if (i && a) {
                var p = (function (e, t) {
                        return Y("number" != typeof (e = "function" == typeof e ? e(Object.assign({}, t.rects, { placement: t.placement })) : e) ? e : G(e, k));
                    })(o.padding, n),
                    u = g(i),
                    l = "y" === f ? D : P,
                    d = "y" === f ? A : L,
                    h = n.rects.reference[c] + n.rects.reference[f] - a[f] - n.rects.popper[c],
                    m = a[f] - n.rects.reference[f],
                    v = E(i),
                    y = v ? ("y" === f ? v.clientHeight || 0 : v.clientWidth || 0) : 0,
                    b = h / 2 - m / 2,
                    x = p[l],
                    w = y - u[c] - p[d],
                    O = y / 2 - u[c] / 2 + b,
                    j = de(x, O, w),
                    M = f;
                n.modifiersData[r] = (((t = {})[M] = j), (t.centerOffset = j - O), t);
            }
        },
        effect: function (e) {
            var t = e.state,
                n = e.options.element,
                r = void 0 === n ? "[data-popper-arrow]" : n;
            null != r && ("string" != typeof r || (r = t.elements.popper.querySelector(r))) && C(t.elements.popper, r) && (t.elements.arrow = r);
        },
        requires: ["popperOffsets"],
        requiresIfExists: ["preventOverflow"],
    };
    function ve(e, t, n) {
        return void 0 === n && (n = { x: 0, y: 0 }), { top: e.top - t.height - n.y, right: e.right - t.width + n.x, bottom: e.bottom - t.height + n.y, left: e.left - t.width - n.x };
    }
    function ye(e) {
        return [D, L, A, P].some(function (t) {
            return e[t] >= 0;
        });
    }
    var ge = {
            name: "hide",
            enabled: !0,
            phase: "main",
            requiresIfExists: ["preventOverflow"],
            fn: function (e) {
                var t = e.state,
                    n = e.name,
                    r = t.rects.reference,
                    o = t.rects.popper,
                    i = t.modifiersData.preventOverflow,
                    a = J(t, { elementContext: "reference" }),
                    s = J(t, { altBoundary: !0 }),
                    f = ve(a, r),
                    c = ve(s, o, i),
                    p = ye(f),
                    u = ye(c);
                (t.modifiersData[n] = { referenceClippingOffsets: f, popperEscapeOffsets: c, isReferenceHidden: p, hasPopperEscaped: u }),
                    (t.attributes.popper = Object.assign({}, t.attributes.popper, { "data-popper-reference-hidden": p, "data-popper-escaped": u }));
            },
        },
        be = Z({ defaultModifiers: [ee, te, oe, ie] }),
        xe = [ee, te, oe, ie, ae, le, he, me, ge],
        we = Z({ defaultModifiers: xe });
    (e.applyStyles = ie),
        (e.arrow = me),
        (e.computeStyles = oe),
        (e.createPopper = we),
        (e.createPopperLite = be),
        (e.defaultModifiers = xe),
        (e.detectOverflow = J),
        (e.eventListeners = ee),
        (e.flip = le),
        (e.hide = ge),
        (e.offset = ae),
        (e.popperGenerator = Z),
        (e.popperOffsets = te),
        (e.preventOverflow = he),
        Object.defineProperty(e, "__esModule", { value: !0 });
});

/*! jQuery v3.6.3 | (c) OpenJS Foundation and other contributors | jquery.org/license */
!(function (e, t) {
    "use strict";
    "object" == typeof module && "object" == typeof module.exports
        ? (module.exports = e.document
              ? t(e, !0)
              : function (e) {
                    if (!e.document) throw new Error("jQuery requires a window with a document");
                    return t(e);
                })
        : t(e);
})("undefined" != typeof window ? window : this, function (C, e) {
    "use strict";
    var t = [],
        r = Object.getPrototypeOf,
        s = t.slice,
        g = t.flat
            ? function (e) {
                  return t.flat.call(e);
              }
            : function (e) {
                  return t.concat.apply([], e);
              },
        u = t.push,
        i = t.indexOf,
        n = {},
        o = n.toString,
        y = n.hasOwnProperty,
        a = y.toString,
        l = a.call(Object),
        v = {},
        m = function (e) {
            return "function" == typeof e && "number" != typeof e.nodeType && "function" != typeof e.item;
        },
        x = function (e) {
            return null != e && e === e.window;
        },
        S = C.document,
        c = { type: !0, src: !0, nonce: !0, noModule: !0 };
    function b(e, t, n) {
        var r,
            i,
            o = (n = n || S).createElement("script");
        if (((o.text = e), t)) for (r in c) (i = t[r] || (t.getAttribute && t.getAttribute(r))) && o.setAttribute(r, i);
        n.head.appendChild(o).parentNode.removeChild(o);
    }
    function w(e) {
        return null == e ? e + "" : "object" == typeof e || "function" == typeof e ? n[o.call(e)] || "object" : typeof e;
    }
    var f = "3.6.3",
        E = function (e, t) {
            return new E.fn.init(e, t);
        };
    function p(e) {
        var t = !!e && "length" in e && e.length,
            n = w(e);
        return !m(e) && !x(e) && ("array" === n || 0 === t || ("number" == typeof t && 0 < t && t - 1 in e));
    }
    (E.fn = E.prototype = {
        jquery: f,
        constructor: E,
        length: 0,
        toArray: function () {
            return s.call(this);
        },
        get: function (e) {
            return null == e ? s.call(this) : e < 0 ? this[e + this.length] : this[e];
        },
        pushStack: function (e) {
            var t = E.merge(this.constructor(), e);
            return (t.prevObject = this), t;
        },
        each: function (e) {
            return E.each(this, e);
        },
        map: function (n) {
            return this.pushStack(
                E.map(this, function (e, t) {
                    return n.call(e, t, e);
                })
            );
        },
        slice: function () {
            return this.pushStack(s.apply(this, arguments));
        },
        first: function () {
            return this.eq(0);
        },
        last: function () {
            return this.eq(-1);
        },
        even: function () {
            return this.pushStack(
                E.grep(this, function (e, t) {
                    return (t + 1) % 2;
                })
            );
        },
        odd: function () {
            return this.pushStack(
                E.grep(this, function (e, t) {
                    return t % 2;
                })
            );
        },
        eq: function (e) {
            var t = this.length,
                n = +e + (e < 0 ? t : 0);
            return this.pushStack(0 <= n && n < t ? [this[n]] : []);
        },
        end: function () {
            return this.prevObject || this.constructor();
        },
        push: u,
        sort: t.sort,
        splice: t.splice,
    }),
        (E.extend = E.fn.extend = function () {
            var e,
                t,
                n,
                r,
                i,
                o,
                a = arguments[0] || {},
                s = 1,
                u = arguments.length,
                l = !1;
            for ("boolean" == typeof a && ((l = a), (a = arguments[s] || {}), s++), "object" == typeof a || m(a) || (a = {}), s === u && ((a = this), s--); s < u; s++)
                if (null != (e = arguments[s]))
                    for (t in e)
                        (r = e[t]),
                            "__proto__" !== t &&
                                a !== r &&
                                (l && r && (E.isPlainObject(r) || (i = Array.isArray(r)))
                                    ? ((n = a[t]), (o = i && !Array.isArray(n) ? [] : i || E.isPlainObject(n) ? n : {}), (i = !1), (a[t] = E.extend(l, o, r)))
                                    : void 0 !== r && (a[t] = r));
            return a;
        }),
        E.extend({
            expando: "jQuery" + (f + Math.random()).replace(/\D/g, ""),
            isReady: !0,
            error: function (e) {
                throw new Error(e);
            },
            noop: function () {},
            isPlainObject: function (e) {
                var t, n;
                return !(!e || "[object Object]" !== o.call(e)) && (!(t = r(e)) || ("function" == typeof (n = y.call(t, "constructor") && t.constructor) && a.call(n) === l));
            },
            isEmptyObject: function (e) {
                var t;
                for (t in e) return !1;
                return !0;
            },
            globalEval: function (e, t, n) {
                b(e, { nonce: t && t.nonce }, n);
            },
            each: function (e, t) {
                var n,
                    r = 0;
                if (p(e)) {
                    for (n = e.length; r < n; r++) if (!1 === t.call(e[r], r, e[r])) break;
                } else for (r in e) if (!1 === t.call(e[r], r, e[r])) break;
                return e;
            },
            makeArray: function (e, t) {
                var n = t || [];
                return null != e && (p(Object(e)) ? E.merge(n, "string" == typeof e ? [e] : e) : u.call(n, e)), n;
            },
            inArray: function (e, t, n) {
                return null == t ? -1 : i.call(t, e, n);
            },
            merge: function (e, t) {
                for (var n = +t.length, r = 0, i = e.length; r < n; r++) e[i++] = t[r];
                return (e.length = i), e;
            },
            grep: function (e, t, n) {
                for (var r = [], i = 0, o = e.length, a = !n; i < o; i++) !t(e[i], i) !== a && r.push(e[i]);
                return r;
            },
            map: function (e, t, n) {
                var r,
                    i,
                    o = 0,
                    a = [];
                if (p(e)) for (r = e.length; o < r; o++) null != (i = t(e[o], o, n)) && a.push(i);
                else for (o in e) null != (i = t(e[o], o, n)) && a.push(i);
                return g(a);
            },
            guid: 1,
            support: v,
        }),
        "function" == typeof Symbol && (E.fn[Symbol.iterator] = t[Symbol.iterator]),
        E.each("Boolean Number String Function Array Date RegExp Object Error Symbol".split(" "), function (e, t) {
            n["[object " + t + "]"] = t.toLowerCase();
        });
    var d = (function (n) {
        var e,
            d,
            b,
            o,
            i,
            h,
            f,
            g,
            w,
            u,
            l,
            T,
            C,
            a,
            S,
            y,
            s,
            c,
            v,
            E = "sizzle" + 1 * new Date(),
            p = n.document,
            k = 0,
            r = 0,
            m = ue(),
            x = ue(),
            A = ue(),
            N = ue(),
            j = function (e, t) {
                return e === t && (l = !0), 0;
            },
            D = {}.hasOwnProperty,
            t = [],
            q = t.pop,
            L = t.push,
            H = t.push,
            O = t.slice,
            P = function (e, t) {
                for (var n = 0, r = e.length; n < r; n++) if (e[n] === t) return n;
                return -1;
            },
            R = "checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|loop|multiple|open|readonly|required|scoped",
            M = "[\\x20\\t\\r\\n\\f]",
            I = "(?:\\\\[\\da-fA-F]{1,6}" + M + "?|\\\\[^\\r\\n\\f]|[\\w-]|[^\0-\\x7f])+",
            W = "\\[" + M + "*(" + I + ")(?:" + M + "*([*^$|!~]?=)" + M + "*(?:'((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\"|(" + I + "))|)" + M + "*\\]",
            F = ":(" + I + ")(?:\\((('((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\")|((?:\\\\.|[^\\\\()[\\]]|" + W + ")*)|.*)\\)|)",
            $ = new RegExp(M + "+", "g"),
            B = new RegExp("^" + M + "+|((?:^|[^\\\\])(?:\\\\.)*)" + M + "+$", "g"),
            _ = new RegExp("^" + M + "*," + M + "*"),
            z = new RegExp("^" + M + "*([>+~]|" + M + ")" + M + "*"),
            U = new RegExp(M + "|>"),
            X = new RegExp(F),
            V = new RegExp("^" + I + "$"),
            G = {
                ID: new RegExp("^#(" + I + ")"),
                CLASS: new RegExp("^\\.(" + I + ")"),
                TAG: new RegExp("^(" + I + "|[*])"),
                ATTR: new RegExp("^" + W),
                PSEUDO: new RegExp("^" + F),
                CHILD: new RegExp("^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\(" + M + "*(even|odd|(([+-]|)(\\d*)n|)" + M + "*(?:([+-]|)" + M + "*(\\d+)|))" + M + "*\\)|)", "i"),
                bool: new RegExp("^(?:" + R + ")$", "i"),
                needsContext: new RegExp("^" + M + "*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\(" + M + "*((?:-\\d)?\\d*)" + M + "*\\)|)(?=[^-]|$)", "i"),
            },
            Y = /HTML$/i,
            Q = /^(?:input|select|textarea|button)$/i,
            J = /^h\d$/i,
            K = /^[^{]+\{\s*\[native \w/,
            Z = /^(?:#([\w-]+)|(\w+)|\.([\w-]+))$/,
            ee = /[+~]/,
            te = new RegExp("\\\\[\\da-fA-F]{1,6}" + M + "?|\\\\([^\\r\\n\\f])", "g"),
            ne = function (e, t) {
                var n = "0x" + e.slice(1) - 65536;
                return t || (n < 0 ? String.fromCharCode(n + 65536) : String.fromCharCode((n >> 10) | 55296, (1023 & n) | 56320));
            },
            re = /([\0-\x1f\x7f]|^-?\d)|^-$|[^\0-\x1f\x7f-\uFFFF\w-]/g,
            ie = function (e, t) {
                return t ? ("\0" === e ? "\ufffd" : e.slice(0, -1) + "\\" + e.charCodeAt(e.length - 1).toString(16) + " ") : "\\" + e;
            },
            oe = function () {
                T();
            },
            ae = be(
                function (e) {
                    return !0 === e.disabled && "fieldset" === e.nodeName.toLowerCase();
                },
                { dir: "parentNode", next: "legend" }
            );
        try {
            H.apply((t = O.call(p.childNodes)), p.childNodes), t[p.childNodes.length].nodeType;
        } catch (e) {
            H = {
                apply: t.length
                    ? function (e, t) {
                          L.apply(e, O.call(t));
                      }
                    : function (e, t) {
                          var n = e.length,
                              r = 0;
                          while ((e[n++] = t[r++]));
                          e.length = n - 1;
                      },
            };
        }
        function se(t, e, n, r) {
            var i,
                o,
                a,
                s,
                u,
                l,
                c,
                f = e && e.ownerDocument,
                p = e ? e.nodeType : 9;
            if (((n = n || []), "string" != typeof t || !t || (1 !== p && 9 !== p && 11 !== p))) return n;
            if (!r && (T(e), (e = e || C), S)) {
                if (11 !== p && (u = Z.exec(t)))
                    if ((i = u[1])) {
                        if (9 === p) {
                            if (!(a = e.getElementById(i))) return n;
                            if (a.id === i) return n.push(a), n;
                        } else if (f && (a = f.getElementById(i)) && v(e, a) && a.id === i) return n.push(a), n;
                    } else {
                        if (u[2]) return H.apply(n, e.getElementsByTagName(t)), n;
                        if ((i = u[3]) && d.getElementsByClassName && e.getElementsByClassName) return H.apply(n, e.getElementsByClassName(i)), n;
                    }
                if (d.qsa && !N[t + " "] && (!y || !y.test(t)) && (1 !== p || "object" !== e.nodeName.toLowerCase())) {
                    if (((c = t), (f = e), 1 === p && (U.test(t) || z.test(t)))) {
                        ((f = (ee.test(t) && ve(e.parentNode)) || e) === e && d.scope) || ((s = e.getAttribute("id")) ? (s = s.replace(re, ie)) : e.setAttribute("id", (s = E))), (o = (l = h(t)).length);
                        while (o--) l[o] = (s ? "#" + s : ":scope") + " " + xe(l[o]);
                        c = l.join(",");
                    }
                    try {
                        if (d.cssSupportsSelector && !CSS.supports("selector(:is(" + c + "))")) throw new Error();
                        return H.apply(n, f.querySelectorAll(c)), n;
                    } catch (e) {
                        N(t, !0);
                    } finally {
                        s === E && e.removeAttribute("id");
                    }
                }
            }
            return g(t.replace(B, "$1"), e, n, r);
        }
        function ue() {
            var r = [];
            return function e(t, n) {
                return r.push(t + " ") > b.cacheLength && delete e[r.shift()], (e[t + " "] = n);
            };
        }
        function le(e) {
            return (e[E] = !0), e;
        }
        function ce(e) {
            var t = C.createElement("fieldset");
            try {
                return !!e(t);
            } catch (e) {
                return !1;
            } finally {
                t.parentNode && t.parentNode.removeChild(t), (t = null);
            }
        }
        function fe(e, t) {
            var n = e.split("|"),
                r = n.length;
            while (r--) b.attrHandle[n[r]] = t;
        }
        function pe(e, t) {
            var n = t && e,
                r = n && 1 === e.nodeType && 1 === t.nodeType && e.sourceIndex - t.sourceIndex;
            if (r) return r;
            if (n) while ((n = n.nextSibling)) if (n === t) return -1;
            return e ? 1 : -1;
        }
        function de(t) {
            return function (e) {
                return "input" === e.nodeName.toLowerCase() && e.type === t;
            };
        }
        function he(n) {
            return function (e) {
                var t = e.nodeName.toLowerCase();
                return ("input" === t || "button" === t) && e.type === n;
            };
        }
        function ge(t) {
            return function (e) {
                return "form" in e
                    ? e.parentNode && !1 === e.disabled
                        ? "label" in e
                            ? "label" in e.parentNode
                                ? e.parentNode.disabled === t
                                : e.disabled === t
                            : e.isDisabled === t || (e.isDisabled !== !t && ae(e) === t)
                        : e.disabled === t
                    : "label" in e && e.disabled === t;
            };
        }
        function ye(a) {
            return le(function (o) {
                return (
                    (o = +o),
                    le(function (e, t) {
                        var n,
                            r = a([], e.length, o),
                            i = r.length;
                        while (i--) e[(n = r[i])] && (e[n] = !(t[n] = e[n]));
                    })
                );
            });
        }
        function ve(e) {
            return e && "undefined" != typeof e.getElementsByTagName && e;
        }
        for (e in ((d = se.support = {}),
        (i = se.isXML = function (e) {
            var t = e && e.namespaceURI,
                n = e && (e.ownerDocument || e).documentElement;
            return !Y.test(t || (n && n.nodeName) || "HTML");
        }),
        (T = se.setDocument = function (e) {
            var t,
                n,
                r = e ? e.ownerDocument || e : p;
            return (
                r != C &&
                    9 === r.nodeType &&
                    r.documentElement &&
                    ((a = (C = r).documentElement),
                    (S = !i(C)),
                    p != C && (n = C.defaultView) && n.top !== n && (n.addEventListener ? n.addEventListener("unload", oe, !1) : n.attachEvent && n.attachEvent("onunload", oe)),
                    (d.scope = ce(function (e) {
                        return a.appendChild(e).appendChild(C.createElement("div")), "undefined" != typeof e.querySelectorAll && !e.querySelectorAll(":scope fieldset div").length;
                    })),
                    (d.cssSupportsSelector = ce(function () {
                        return CSS.supports("selector(*)") && C.querySelectorAll(":is(:jqfake)") && !CSS.supports("selector(:is(*,:jqfake))");
                    })),
                    (d.attributes = ce(function (e) {
                        return (e.className = "i"), !e.getAttribute("className");
                    })),
                    (d.getElementsByTagName = ce(function (e) {
                        return e.appendChild(C.createComment("")), !e.getElementsByTagName("*").length;
                    })),
                    (d.getElementsByClassName = K.test(C.getElementsByClassName)),
                    (d.getById = ce(function (e) {
                        return (a.appendChild(e).id = E), !C.getElementsByName || !C.getElementsByName(E).length;
                    })),
                    d.getById
                        ? ((b.filter.ID = function (e) {
                              var t = e.replace(te, ne);
                              return function (e) {
                                  return e.getAttribute("id") === t;
                              };
                          }),
                          (b.find.ID = function (e, t) {
                              if ("undefined" != typeof t.getElementById && S) {
                                  var n = t.getElementById(e);
                                  return n ? [n] : [];
                              }
                          }))
                        : ((b.filter.ID = function (e) {
                              var n = e.replace(te, ne);
                              return function (e) {
                                  var t = "undefined" != typeof e.getAttributeNode && e.getAttributeNode("id");
                                  return t && t.value === n;
                              };
                          }),
                          (b.find.ID = function (e, t) {
                              if ("undefined" != typeof t.getElementById && S) {
                                  var n,
                                      r,
                                      i,
                                      o = t.getElementById(e);
                                  if (o) {
                                      if ((n = o.getAttributeNode("id")) && n.value === e) return [o];
                                      (i = t.getElementsByName(e)), (r = 0);
                                      while ((o = i[r++])) if ((n = o.getAttributeNode("id")) && n.value === e) return [o];
                                  }
                                  return [];
                              }
                          })),
                    (b.find.TAG = d.getElementsByTagName
                        ? function (e, t) {
                              return "undefined" != typeof t.getElementsByTagName ? t.getElementsByTagName(e) : d.qsa ? t.querySelectorAll(e) : void 0;
                          }
                        : function (e, t) {
                              var n,
                                  r = [],
                                  i = 0,
                                  o = t.getElementsByTagName(e);
                              if ("*" === e) {
                                  while ((n = o[i++])) 1 === n.nodeType && r.push(n);
                                  return r;
                              }
                              return o;
                          }),
                    (b.find.CLASS =
                        d.getElementsByClassName &&
                        function (e, t) {
                            if ("undefined" != typeof t.getElementsByClassName && S) return t.getElementsByClassName(e);
                        }),
                    (s = []),
                    (y = []),
                    (d.qsa = K.test(C.querySelectorAll)) &&
                        (ce(function (e) {
                            var t;
                            (a.appendChild(e).innerHTML = "<a id='" + E + "'></a><select id='" + E + "-\r\\' msallowcapture=''><option selected=''></option></select>"),
                                e.querySelectorAll("[msallowcapture^='']").length && y.push("[*^$]=" + M + "*(?:''|\"\")"),
                                e.querySelectorAll("[selected]").length || y.push("\\[" + M + "*(?:value|" + R + ")"),
                                e.querySelectorAll("[id~=" + E + "-]").length || y.push("~="),
                                (t = C.createElement("input")).setAttribute("name", ""),
                                e.appendChild(t),
                                e.querySelectorAll("[name='']").length || y.push("\\[" + M + "*name" + M + "*=" + M + "*(?:''|\"\")"),
                                e.querySelectorAll(":checked").length || y.push(":checked"),
                                e.querySelectorAll("a#" + E + "+*").length || y.push(".#.+[+~]"),
                                e.querySelectorAll("\\\f"),
                                y.push("[\\r\\n\\f]");
                        }),
                        ce(function (e) {
                            e.innerHTML = "<a href='' disabled='disabled'></a><select disabled='disabled'><option/></select>";
                            var t = C.createElement("input");
                            t.setAttribute("type", "hidden"),
                                e.appendChild(t).setAttribute("name", "D"),
                                e.querySelectorAll("[name=d]").length && y.push("name" + M + "*[*^$|!~]?="),
                                2 !== e.querySelectorAll(":enabled").length && y.push(":enabled", ":disabled"),
                                (a.appendChild(e).disabled = !0),
                                2 !== e.querySelectorAll(":disabled").length && y.push(":enabled", ":disabled"),
                                e.querySelectorAll("*,:x"),
                                y.push(",.*:");
                        })),
                    (d.matchesSelector = K.test((c = a.matches || a.webkitMatchesSelector || a.mozMatchesSelector || a.oMatchesSelector || a.msMatchesSelector))) &&
                        ce(function (e) {
                            (d.disconnectedMatch = c.call(e, "*")), c.call(e, "[s!='']:x"), s.push("!=", F);
                        }),
                    d.cssSupportsSelector || y.push(":has"),
                    (y = y.length && new RegExp(y.join("|"))),
                    (s = s.length && new RegExp(s.join("|"))),
                    (t = K.test(a.compareDocumentPosition)),
                    (v =
                        t || K.test(a.contains)
                            ? function (e, t) {
                                  var n = (9 === e.nodeType && e.documentElement) || e,
                                      r = t && t.parentNode;
                                  return e === r || !(!r || 1 !== r.nodeType || !(n.contains ? n.contains(r) : e.compareDocumentPosition && 16 & e.compareDocumentPosition(r)));
                              }
                            : function (e, t) {
                                  if (t) while ((t = t.parentNode)) if (t === e) return !0;
                                  return !1;
                              }),
                    (j = t
                        ? function (e, t) {
                              if (e === t) return (l = !0), 0;
                              var n = !e.compareDocumentPosition - !t.compareDocumentPosition;
                              return (
                                  n ||
                                  (1 & (n = (e.ownerDocument || e) == (t.ownerDocument || t) ? e.compareDocumentPosition(t) : 1) || (!d.sortDetached && t.compareDocumentPosition(e) === n)
                                      ? e == C || (e.ownerDocument == p && v(p, e))
                                          ? -1
                                          : t == C || (t.ownerDocument == p && v(p, t))
                                          ? 1
                                          : u
                                          ? P(u, e) - P(u, t)
                                          : 0
                                      : 4 & n
                                      ? -1
                                      : 1)
                              );
                          }
                        : function (e, t) {
                              if (e === t) return (l = !0), 0;
                              var n,
                                  r = 0,
                                  i = e.parentNode,
                                  o = t.parentNode,
                                  a = [e],
                                  s = [t];
                              if (!i || !o) return e == C ? -1 : t == C ? 1 : i ? -1 : o ? 1 : u ? P(u, e) - P(u, t) : 0;
                              if (i === o) return pe(e, t);
                              n = e;
                              while ((n = n.parentNode)) a.unshift(n);
                              n = t;
                              while ((n = n.parentNode)) s.unshift(n);
                              while (a[r] === s[r]) r++;
                              return r ? pe(a[r], s[r]) : a[r] == p ? -1 : s[r] == p ? 1 : 0;
                          })),
                C
            );
        }),
        (se.matches = function (e, t) {
            return se(e, null, null, t);
        }),
        (se.matchesSelector = function (e, t) {
            if ((T(e), d.matchesSelector && S && !N[t + " "] && (!s || !s.test(t)) && (!y || !y.test(t))))
                try {
                    var n = c.call(e, t);
                    if (n || d.disconnectedMatch || (e.document && 11 !== e.document.nodeType)) return n;
                } catch (e) {
                    N(t, !0);
                }
            return 0 < se(t, C, null, [e]).length;
        }),
        (se.contains = function (e, t) {
            return (e.ownerDocument || e) != C && T(e), v(e, t);
        }),
        (se.attr = function (e, t) {
            (e.ownerDocument || e) != C && T(e);
            var n = b.attrHandle[t.toLowerCase()],
                r = n && D.call(b.attrHandle, t.toLowerCase()) ? n(e, t, !S) : void 0;
            return void 0 !== r ? r : d.attributes || !S ? e.getAttribute(t) : (r = e.getAttributeNode(t)) && r.specified ? r.value : null;
        }),
        (se.escape = function (e) {
            return (e + "").replace(re, ie);
        }),
        (se.error = function (e) {
            throw new Error("Syntax error, unrecognized expression: " + e);
        }),
        (se.uniqueSort = function (e) {
            var t,
                n = [],
                r = 0,
                i = 0;
            if (((l = !d.detectDuplicates), (u = !d.sortStable && e.slice(0)), e.sort(j), l)) {
                while ((t = e[i++])) t === e[i] && (r = n.push(i));
                while (r--) e.splice(n[r], 1);
            }
            return (u = null), e;
        }),
        (o = se.getText = function (e) {
            var t,
                n = "",
                r = 0,
                i = e.nodeType;
            if (i) {
                if (1 === i || 9 === i || 11 === i) {
                    if ("string" == typeof e.textContent) return e.textContent;
                    for (e = e.firstChild; e; e = e.nextSibling) n += o(e);
                } else if (3 === i || 4 === i) return e.nodeValue;
            } else while ((t = e[r++])) n += o(t);
            return n;
        }),
        ((b = se.selectors = {
            cacheLength: 50,
            createPseudo: le,
            match: G,
            attrHandle: {},
            find: {},
            relative: { ">": { dir: "parentNode", first: !0 }, " ": { dir: "parentNode" }, "+": { dir: "previousSibling", first: !0 }, "~": { dir: "previousSibling" } },
            preFilter: {
                ATTR: function (e) {
                    return (e[1] = e[1].replace(te, ne)), (e[3] = (e[3] || e[4] || e[5] || "").replace(te, ne)), "~=" === e[2] && (e[3] = " " + e[3] + " "), e.slice(0, 4);
                },
                CHILD: function (e) {
                    return (
                        (e[1] = e[1].toLowerCase()),
                        "nth" === e[1].slice(0, 3) ? (e[3] || se.error(e[0]), (e[4] = +(e[4] ? e[5] + (e[6] || 1) : 2 * ("even" === e[3] || "odd" === e[3]))), (e[5] = +(e[7] + e[8] || "odd" === e[3]))) : e[3] && se.error(e[0]),
                        e
                    );
                },
                PSEUDO: function (e) {
                    var t,
                        n = !e[6] && e[2];
                    return G.CHILD.test(e[0])
                        ? null
                        : (e[3] ? (e[2] = e[4] || e[5] || "") : n && X.test(n) && (t = h(n, !0)) && (t = n.indexOf(")", n.length - t) - n.length) && ((e[0] = e[0].slice(0, t)), (e[2] = n.slice(0, t))), e.slice(0, 3));
                },
            },
            filter: {
                TAG: function (e) {
                    var t = e.replace(te, ne).toLowerCase();
                    return "*" === e
                        ? function () {
                              return !0;
                          }
                        : function (e) {
                              return e.nodeName && e.nodeName.toLowerCase() === t;
                          };
                },
                CLASS: function (e) {
                    var t = m[e + " "];
                    return (
                        t ||
                        ((t = new RegExp("(^|" + M + ")" + e + "(" + M + "|$)")) &&
                            m(e, function (e) {
                                return t.test(("string" == typeof e.className && e.className) || ("undefined" != typeof e.getAttribute && e.getAttribute("class")) || "");
                            }))
                    );
                },
                ATTR: function (n, r, i) {
                    return function (e) {
                        var t = se.attr(e, n);
                        return null == t
                            ? "!=" === r
                            : !r ||
                                  ((t += ""),
                                  "=" === r
                                      ? t === i
                                      : "!=" === r
                                      ? t !== i
                                      : "^=" === r
                                      ? i && 0 === t.indexOf(i)
                                      : "*=" === r
                                      ? i && -1 < t.indexOf(i)
                                      : "$=" === r
                                      ? i && t.slice(-i.length) === i
                                      : "~=" === r
                                      ? -1 < (" " + t.replace($, " ") + " ").indexOf(i)
                                      : "|=" === r && (t === i || t.slice(0, i.length + 1) === i + "-"));
                    };
                },
                CHILD: function (h, e, t, g, y) {
                    var v = "nth" !== h.slice(0, 3),
                        m = "last" !== h.slice(-4),
                        x = "of-type" === e;
                    return 1 === g && 0 === y
                        ? function (e) {
                              return !!e.parentNode;
                          }
                        : function (e, t, n) {
                              var r,
                                  i,
                                  o,
                                  a,
                                  s,
                                  u,
                                  l = v !== m ? "nextSibling" : "previousSibling",
                                  c = e.parentNode,
                                  f = x && e.nodeName.toLowerCase(),
                                  p = !n && !x,
                                  d = !1;
                              if (c) {
                                  if (v) {
                                      while (l) {
                                          a = e;
                                          while ((a = a[l])) if (x ? a.nodeName.toLowerCase() === f : 1 === a.nodeType) return !1;
                                          u = l = "only" === h && !u && "nextSibling";
                                      }
                                      return !0;
                                  }
                                  if (((u = [m ? c.firstChild : c.lastChild]), m && p)) {
                                      (d = (s = (r = (i = (o = (a = c)[E] || (a[E] = {}))[a.uniqueID] || (o[a.uniqueID] = {}))[h] || [])[0] === k && r[1]) && r[2]), (a = s && c.childNodes[s]);
                                      while ((a = (++s && a && a[l]) || (d = s = 0) || u.pop()))
                                          if (1 === a.nodeType && ++d && a === e) {
                                              i[h] = [k, s, d];
                                              break;
                                          }
                                  } else if ((p && (d = s = (r = (i = (o = (a = e)[E] || (a[E] = {}))[a.uniqueID] || (o[a.uniqueID] = {}))[h] || [])[0] === k && r[1]), !1 === d))
                                      while ((a = (++s && a && a[l]) || (d = s = 0) || u.pop()))
                                          if ((x ? a.nodeName.toLowerCase() === f : 1 === a.nodeType) && ++d && (p && ((i = (o = a[E] || (a[E] = {}))[a.uniqueID] || (o[a.uniqueID] = {}))[h] = [k, d]), a === e)) break;
                                  return (d -= y) === g || (d % g == 0 && 0 <= d / g);
                              }
                          };
                },
                PSEUDO: function (e, o) {
                    var t,
                        a = b.pseudos[e] || b.setFilters[e.toLowerCase()] || se.error("unsupported pseudo: " + e);
                    return a[E]
                        ? a(o)
                        : 1 < a.length
                        ? ((t = [e, e, "", o]),
                          b.setFilters.hasOwnProperty(e.toLowerCase())
                              ? le(function (e, t) {
                                    var n,
                                        r = a(e, o),
                                        i = r.length;
                                    while (i--) e[(n = P(e, r[i]))] = !(t[n] = r[i]);
                                })
                              : function (e) {
                                    return a(e, 0, t);
                                })
                        : a;
                },
            },
            pseudos: {
                not: le(function (e) {
                    var r = [],
                        i = [],
                        s = f(e.replace(B, "$1"));
                    return s[E]
                        ? le(function (e, t, n, r) {
                              var i,
                                  o = s(e, null, r, []),
                                  a = e.length;
                              while (a--) (i = o[a]) && (e[a] = !(t[a] = i));
                          })
                        : function (e, t, n) {
                              return (r[0] = e), s(r, null, n, i), (r[0] = null), !i.pop();
                          };
                }),
                has: le(function (t) {
                    return function (e) {
                        return 0 < se(t, e).length;
                    };
                }),
                contains: le(function (t) {
                    return (
                        (t = t.replace(te, ne)),
                        function (e) {
                            return -1 < (e.textContent || o(e)).indexOf(t);
                        }
                    );
                }),
                lang: le(function (n) {
                    return (
                        V.test(n || "") || se.error("unsupported lang: " + n),
                        (n = n.replace(te, ne).toLowerCase()),
                        function (e) {
                            var t;
                            do {
                                if ((t = S ? e.lang : e.getAttribute("xml:lang") || e.getAttribute("lang"))) return (t = t.toLowerCase()) === n || 0 === t.indexOf(n + "-");
                            } while ((e = e.parentNode) && 1 === e.nodeType);
                            return !1;
                        }
                    );
                }),
                target: function (e) {
                    var t = n.location && n.location.hash;
                    return t && t.slice(1) === e.id;
                },
                root: function (e) {
                    return e === a;
                },
                focus: function (e) {
                    return e === C.activeElement && (!C.hasFocus || C.hasFocus()) && !!(e.type || e.href || ~e.tabIndex);
                },
                enabled: ge(!1),
                disabled: ge(!0),
                checked: function (e) {
                    var t = e.nodeName.toLowerCase();
                    return ("input" === t && !!e.checked) || ("option" === t && !!e.selected);
                },
                selected: function (e) {
                    return e.parentNode && e.parentNode.selectedIndex, !0 === e.selected;
                },
                empty: function (e) {
                    for (e = e.firstChild; e; e = e.nextSibling) if (e.nodeType < 6) return !1;
                    return !0;
                },
                parent: function (e) {
                    return !b.pseudos.empty(e);
                },
                header: function (e) {
                    return J.test(e.nodeName);
                },
                input: function (e) {
                    return Q.test(e.nodeName);
                },
                button: function (e) {
                    var t = e.nodeName.toLowerCase();
                    return ("input" === t && "button" === e.type) || "button" === t;
                },
                text: function (e) {
                    var t;
                    return "input" === e.nodeName.toLowerCase() && "text" === e.type && (null == (t = e.getAttribute("type")) || "text" === t.toLowerCase());
                },
                first: ye(function () {
                    return [0];
                }),
                last: ye(function (e, t) {
                    return [t - 1];
                }),
                eq: ye(function (e, t, n) {
                    return [n < 0 ? n + t : n];
                }),
                even: ye(function (e, t) {
                    for (var n = 0; n < t; n += 2) e.push(n);
                    return e;
                }),
                odd: ye(function (e, t) {
                    for (var n = 1; n < t; n += 2) e.push(n);
                    return e;
                }),
                lt: ye(function (e, t, n) {
                    for (var r = n < 0 ? n + t : t < n ? t : n; 0 <= --r; ) e.push(r);
                    return e;
                }),
                gt: ye(function (e, t, n) {
                    for (var r = n < 0 ? n + t : n; ++r < t; ) e.push(r);
                    return e;
                }),
            },
        }).pseudos.nth = b.pseudos.eq),
        { radio: !0, checkbox: !0, file: !0, password: !0, image: !0 }))
            b.pseudos[e] = de(e);
        for (e in { submit: !0, reset: !0 }) b.pseudos[e] = he(e);
        function me() {}
        function xe(e) {
            for (var t = 0, n = e.length, r = ""; t < n; t++) r += e[t].value;
            return r;
        }
        function be(s, e, t) {
            var u = e.dir,
                l = e.next,
                c = l || u,
                f = t && "parentNode" === c,
                p = r++;
            return e.first
                ? function (e, t, n) {
                      while ((e = e[u])) if (1 === e.nodeType || f) return s(e, t, n);
                      return !1;
                  }
                : function (e, t, n) {
                      var r,
                          i,
                          o,
                          a = [k, p];
                      if (n) {
                          while ((e = e[u])) if ((1 === e.nodeType || f) && s(e, t, n)) return !0;
                      } else
                          while ((e = e[u]))
                              if (1 === e.nodeType || f)
                                  if (((i = (o = e[E] || (e[E] = {}))[e.uniqueID] || (o[e.uniqueID] = {})), l && l === e.nodeName.toLowerCase())) e = e[u] || e;
                                  else {
                                      if ((r = i[c]) && r[0] === k && r[1] === p) return (a[2] = r[2]);
                                      if (((i[c] = a)[2] = s(e, t, n))) return !0;
                                  }
                      return !1;
                  };
        }
        function we(i) {
            return 1 < i.length
                ? function (e, t, n) {
                      var r = i.length;
                      while (r--) if (!i[r](e, t, n)) return !1;
                      return !0;
                  }
                : i[0];
        }
        function Te(e, t, n, r, i) {
            for (var o, a = [], s = 0, u = e.length, l = null != t; s < u; s++) (o = e[s]) && ((n && !n(o, r, i)) || (a.push(o), l && t.push(s)));
            return a;
        }
        function Ce(d, h, g, y, v, e) {
            return (
                y && !y[E] && (y = Ce(y)),
                v && !v[E] && (v = Ce(v, e)),
                le(function (e, t, n, r) {
                    var i,
                        o,
                        a,
                        s = [],
                        u = [],
                        l = t.length,
                        c =
                            e ||
                            (function (e, t, n) {
                                for (var r = 0, i = t.length; r < i; r++) se(e, t[r], n);
                                return n;
                            })(h || "*", n.nodeType ? [n] : n, []),
                        f = !d || (!e && h) ? c : Te(c, s, d, n, r),
                        p = g ? (v || (e ? d : l || y) ? [] : t) : f;
                    if ((g && g(f, p, n, r), y)) {
                        (i = Te(p, u)), y(i, [], n, r), (o = i.length);
                        while (o--) (a = i[o]) && (p[u[o]] = !(f[u[o]] = a));
                    }
                    if (e) {
                        if (v || d) {
                            if (v) {
                                (i = []), (o = p.length);
                                while (o--) (a = p[o]) && i.push((f[o] = a));
                                v(null, (p = []), i, r);
                            }
                            o = p.length;
                            while (o--) (a = p[o]) && -1 < (i = v ? P(e, a) : s[o]) && (e[i] = !(t[i] = a));
                        }
                    } else (p = Te(p === t ? p.splice(l, p.length) : p)), v ? v(null, t, p, r) : H.apply(t, p);
                })
            );
        }
        function Se(e) {
            for (
                var i,
                    t,
                    n,
                    r = e.length,
                    o = b.relative[e[0].type],
                    a = o || b.relative[" "],
                    s = o ? 1 : 0,
                    u = be(
                        function (e) {
                            return e === i;
                        },
                        a,
                        !0
                    ),
                    l = be(
                        function (e) {
                            return -1 < P(i, e);
                        },
                        a,
                        !0
                    ),
                    c = [
                        function (e, t, n) {
                            var r = (!o && (n || t !== w)) || ((i = t).nodeType ? u(e, t, n) : l(e, t, n));
                            return (i = null), r;
                        },
                    ];
                s < r;
                s++
            )
                if ((t = b.relative[e[s].type])) c = [be(we(c), t)];
                else {
                    if ((t = b.filter[e[s].type].apply(null, e[s].matches))[E]) {
                        for (n = ++s; n < r; n++) if (b.relative[e[n].type]) break;
                        return Ce(1 < s && we(c), 1 < s && xe(e.slice(0, s - 1).concat({ value: " " === e[s - 2].type ? "*" : "" })).replace(B, "$1"), t, s < n && Se(e.slice(s, n)), n < r && Se((e = e.slice(n))), n < r && xe(e));
                    }
                    c.push(t);
                }
            return we(c);
        }
        return (
            (me.prototype = b.filters = b.pseudos),
            (b.setFilters = new me()),
            (h = se.tokenize = function (e, t) {
                var n,
                    r,
                    i,
                    o,
                    a,
                    s,
                    u,
                    l = x[e + " "];
                if (l) return t ? 0 : l.slice(0);
                (a = e), (s = []), (u = b.preFilter);
                while (a) {
                    for (o in ((n && !(r = _.exec(a))) || (r && (a = a.slice(r[0].length) || a), s.push((i = []))),
                    (n = !1),
                    (r = z.exec(a)) && ((n = r.shift()), i.push({ value: n, type: r[0].replace(B, " ") }), (a = a.slice(n.length))),
                    b.filter))
                        !(r = G[o].exec(a)) || (u[o] && !(r = u[o](r))) || ((n = r.shift()), i.push({ value: n, type: o, matches: r }), (a = a.slice(n.length)));
                    if (!n) break;
                }
                return t ? a.length : a ? se.error(e) : x(e, s).slice(0);
            }),
            (f = se.compile = function (e, t) {
                var n,
                    y,
                    v,
                    m,
                    x,
                    r,
                    i = [],
                    o = [],
                    a = A[e + " "];
                if (!a) {
                    t || (t = h(e)), (n = t.length);
                    while (n--) (a = Se(t[n]))[E] ? i.push(a) : o.push(a);
                    (a = A(
                        e,
                        ((y = o),
                        (m = 0 < (v = i).length),
                        (x = 0 < y.length),
                        (r = function (e, t, n, r, i) {
                            var o,
                                a,
                                s,
                                u = 0,
                                l = "0",
                                c = e && [],
                                f = [],
                                p = w,
                                d = e || (x && b.find.TAG("*", i)),
                                h = (k += null == p ? 1 : Math.random() || 0.1),
                                g = d.length;
                            for (i && (w = t == C || t || i); l !== g && null != (o = d[l]); l++) {
                                if (x && o) {
                                    (a = 0), t || o.ownerDocument == C || (T(o), (n = !S));
                                    while ((s = y[a++]))
                                        if (s(o, t || C, n)) {
                                            r.push(o);
                                            break;
                                        }
                                    i && (k = h);
                                }
                                m && ((o = !s && o) && u--, e && c.push(o));
                            }
                            if (((u += l), m && l !== u)) {
                                a = 0;
                                while ((s = v[a++])) s(c, f, t, n);
                                if (e) {
                                    if (0 < u) while (l--) c[l] || f[l] || (f[l] = q.call(r));
                                    f = Te(f);
                                }
                                H.apply(r, f), i && !e && 0 < f.length && 1 < u + v.length && se.uniqueSort(r);
                            }
                            return i && ((k = h), (w = p)), c;
                        }),
                        m ? le(r) : r)
                    )).selector = e;
                }
                return a;
            }),
            (g = se.select = function (e, t, n, r) {
                var i,
                    o,
                    a,
                    s,
                    u,
                    l = "function" == typeof e && e,
                    c = !r && h((e = l.selector || e));
                if (((n = n || []), 1 === c.length)) {
                    if (2 < (o = c[0] = c[0].slice(0)).length && "ID" === (a = o[0]).type && 9 === t.nodeType && S && b.relative[o[1].type]) {
                        if (!(t = (b.find.ID(a.matches[0].replace(te, ne), t) || [])[0])) return n;
                        l && (t = t.parentNode), (e = e.slice(o.shift().value.length));
                    }
                    i = G.needsContext.test(e) ? 0 : o.length;
                    while (i--) {
                        if (((a = o[i]), b.relative[(s = a.type)])) break;
                        if ((u = b.find[s]) && (r = u(a.matches[0].replace(te, ne), (ee.test(o[0].type) && ve(t.parentNode)) || t))) {
                            if ((o.splice(i, 1), !(e = r.length && xe(o)))) return H.apply(n, r), n;
                            break;
                        }
                    }
                }
                return (l || f(e, c))(r, t, !S, n, !t || (ee.test(e) && ve(t.parentNode)) || t), n;
            }),
            (d.sortStable = E.split("").sort(j).join("") === E),
            (d.detectDuplicates = !!l),
            T(),
            (d.sortDetached = ce(function (e) {
                return 1 & e.compareDocumentPosition(C.createElement("fieldset"));
            })),
            ce(function (e) {
                return (e.innerHTML = "<a href='#'></a>"), "#" === e.firstChild.getAttribute("href");
            }) ||
                fe("type|href|height|width", function (e, t, n) {
                    if (!n) return e.getAttribute(t, "type" === t.toLowerCase() ? 1 : 2);
                }),
            (d.attributes &&
                ce(function (e) {
                    return (e.innerHTML = "<input/>"), e.firstChild.setAttribute("value", ""), "" === e.firstChild.getAttribute("value");
                })) ||
                fe("value", function (e, t, n) {
                    if (!n && "input" === e.nodeName.toLowerCase()) return e.defaultValue;
                }),
            ce(function (e) {
                return null == e.getAttribute("disabled");
            }) ||
                fe(R, function (e, t, n) {
                    var r;
                    if (!n) return !0 === e[t] ? t.toLowerCase() : (r = e.getAttributeNode(t)) && r.specified ? r.value : null;
                }),
            se
        );
    })(C);
    (E.find = d), (E.expr = d.selectors), (E.expr[":"] = E.expr.pseudos), (E.uniqueSort = E.unique = d.uniqueSort), (E.text = d.getText), (E.isXMLDoc = d.isXML), (E.contains = d.contains), (E.escapeSelector = d.escape);
    var h = function (e, t, n) {
            var r = [],
                i = void 0 !== n;
            while ((e = e[t]) && 9 !== e.nodeType)
                if (1 === e.nodeType) {
                    if (i && E(e).is(n)) break;
                    r.push(e);
                }
            return r;
        },
        T = function (e, t) {
            for (var n = []; e; e = e.nextSibling) 1 === e.nodeType && e !== t && n.push(e);
            return n;
        },
        k = E.expr.match.needsContext;
    function A(e, t) {
        return e.nodeName && e.nodeName.toLowerCase() === t.toLowerCase();
    }
    var N = /^<([a-z][^\/\0>:\x20\t\r\n\f]*)[\x20\t\r\n\f]*\/?>(?:<\/\1>|)$/i;
    function j(e, n, r) {
        return m(n)
            ? E.grep(e, function (e, t) {
                  return !!n.call(e, t, e) !== r;
              })
            : n.nodeType
            ? E.grep(e, function (e) {
                  return (e === n) !== r;
              })
            : "string" != typeof n
            ? E.grep(e, function (e) {
                  return -1 < i.call(n, e) !== r;
              })
            : E.filter(n, e, r);
    }
    (E.filter = function (e, t, n) {
        var r = t[0];
        return (
            n && (e = ":not(" + e + ")"),
            1 === t.length && 1 === r.nodeType
                ? E.find.matchesSelector(r, e)
                    ? [r]
                    : []
                : E.find.matches(
                      e,
                      E.grep(t, function (e) {
                          return 1 === e.nodeType;
                      })
                  )
        );
    }),
        E.fn.extend({
            find: function (e) {
                var t,
                    n,
                    r = this.length,
                    i = this;
                if ("string" != typeof e)
                    return this.pushStack(
                        E(e).filter(function () {
                            for (t = 0; t < r; t++) if (E.contains(i[t], this)) return !0;
                        })
                    );
                for (n = this.pushStack([]), t = 0; t < r; t++) E.find(e, i[t], n);
                return 1 < r ? E.uniqueSort(n) : n;
            },
            filter: function (e) {
                return this.pushStack(j(this, e || [], !1));
            },
            not: function (e) {
                return this.pushStack(j(this, e || [], !0));
            },
            is: function (e) {
                return !!j(this, "string" == typeof e && k.test(e) ? E(e) : e || [], !1).length;
            },
        });
    var D,
        q = /^(?:\s*(<[\w\W]+>)[^>]*|#([\w-]+))$/;
    ((E.fn.init = function (e, t, n) {
        var r, i;
        if (!e) return this;
        if (((n = n || D), "string" == typeof e)) {
            if (!(r = "<" === e[0] && ">" === e[e.length - 1] && 3 <= e.length ? [null, e, null] : q.exec(e)) || (!r[1] && t)) return !t || t.jquery ? (t || n).find(e) : this.constructor(t).find(e);
            if (r[1]) {
                if (((t = t instanceof E ? t[0] : t), E.merge(this, E.parseHTML(r[1], t && t.nodeType ? t.ownerDocument || t : S, !0)), N.test(r[1]) && E.isPlainObject(t))) for (r in t) m(this[r]) ? this[r](t[r]) : this.attr(r, t[r]);
                return this;
            }
            return (i = S.getElementById(r[2])) && ((this[0] = i), (this.length = 1)), this;
        }
        return e.nodeType ? ((this[0] = e), (this.length = 1), this) : m(e) ? (void 0 !== n.ready ? n.ready(e) : e(E)) : E.makeArray(e, this);
    }).prototype = E.fn),
        (D = E(S));
    var L = /^(?:parents|prev(?:Until|All))/,
        H = { children: !0, contents: !0, next: !0, prev: !0 };
    function O(e, t) {
        while ((e = e[t]) && 1 !== e.nodeType);
        return e;
    }
    E.fn.extend({
        has: function (e) {
            var t = E(e, this),
                n = t.length;
            return this.filter(function () {
                for (var e = 0; e < n; e++) if (E.contains(this, t[e])) return !0;
            });
        },
        closest: function (e, t) {
            var n,
                r = 0,
                i = this.length,
                o = [],
                a = "string" != typeof e && E(e);
            if (!k.test(e))
                for (; r < i; r++)
                    for (n = this[r]; n && n !== t; n = n.parentNode)
                        if (n.nodeType < 11 && (a ? -1 < a.index(n) : 1 === n.nodeType && E.find.matchesSelector(n, e))) {
                            o.push(n);
                            break;
                        }
            return this.pushStack(1 < o.length ? E.uniqueSort(o) : o);
        },
        index: function (e) {
            return e ? ("string" == typeof e ? i.call(E(e), this[0]) : i.call(this, e.jquery ? e[0] : e)) : this[0] && this[0].parentNode ? this.first().prevAll().length : -1;
        },
        add: function (e, t) {
            return this.pushStack(E.uniqueSort(E.merge(this.get(), E(e, t))));
        },
        addBack: function (e) {
            return this.add(null == e ? this.prevObject : this.prevObject.filter(e));
        },
    }),
        E.each(
            {
                parent: function (e) {
                    var t = e.parentNode;
                    return t && 11 !== t.nodeType ? t : null;
                },
                parents: function (e) {
                    return h(e, "parentNode");
                },
                parentsUntil: function (e, t, n) {
                    return h(e, "parentNode", n);
                },
                next: function (e) {
                    return O(e, "nextSibling");
                },
                prev: function (e) {
                    return O(e, "previousSibling");
                },
                nextAll: function (e) {
                    return h(e, "nextSibling");
                },
                prevAll: function (e) {
                    return h(e, "previousSibling");
                },
                nextUntil: function (e, t, n) {
                    return h(e, "nextSibling", n);
                },
                prevUntil: function (e, t, n) {
                    return h(e, "previousSibling", n);
                },
                siblings: function (e) {
                    return T((e.parentNode || {}).firstChild, e);
                },
                children: function (e) {
                    return T(e.firstChild);
                },
                contents: function (e) {
                    return null != e.contentDocument && r(e.contentDocument) ? e.contentDocument : (A(e, "template") && (e = e.content || e), E.merge([], e.childNodes));
                },
            },
            function (r, i) {
                E.fn[r] = function (e, t) {
                    var n = E.map(this, i, e);
                    return "Until" !== r.slice(-5) && (t = e), t && "string" == typeof t && (n = E.filter(t, n)), 1 < this.length && (H[r] || E.uniqueSort(n), L.test(r) && n.reverse()), this.pushStack(n);
                };
            }
        );
    var P = /[^\x20\t\r\n\f]+/g;
    function R(e) {
        return e;
    }
    function M(e) {
        throw e;
    }
    function I(e, t, n, r) {
        var i;
        try {
            e && m((i = e.promise)) ? i.call(e).done(t).fail(n) : e && m((i = e.then)) ? i.call(e, t, n) : t.apply(void 0, [e].slice(r));
        } catch (e) {
            n.apply(void 0, [e]);
        }
    }
    (E.Callbacks = function (r) {
        var e, n;
        r =
            "string" == typeof r
                ? ((e = r),
                  (n = {}),
                  E.each(e.match(P) || [], function (e, t) {
                      n[t] = !0;
                  }),
                  n)
                : E.extend({}, r);
        var i,
            t,
            o,
            a,
            s = [],
            u = [],
            l = -1,
            c = function () {
                for (a = a || r.once, o = i = !0; u.length; l = -1) {
                    t = u.shift();
                    while (++l < s.length) !1 === s[l].apply(t[0], t[1]) && r.stopOnFalse && ((l = s.length), (t = !1));
                }
                r.memory || (t = !1), (i = !1), a && (s = t ? [] : "");
            },
            f = {
                add: function () {
                    return (
                        s &&
                            (t && !i && ((l = s.length - 1), u.push(t)),
                            (function n(e) {
                                E.each(e, function (e, t) {
                                    m(t) ? (r.unique && f.has(t)) || s.push(t) : t && t.length && "string" !== w(t) && n(t);
                                });
                            })(arguments),
                            t && !i && c()),
                        this
                    );
                },
                remove: function () {
                    return (
                        E.each(arguments, function (e, t) {
                            var n;
                            while (-1 < (n = E.inArray(t, s, n))) s.splice(n, 1), n <= l && l--;
                        }),
                        this
                    );
                },
                has: function (e) {
                    return e ? -1 < E.inArray(e, s) : 0 < s.length;
                },
                empty: function () {
                    return s && (s = []), this;
                },
                disable: function () {
                    return (a = u = []), (s = t = ""), this;
                },
                disabled: function () {
                    return !s;
                },
                lock: function () {
                    return (a = u = []), t || i || (s = t = ""), this;
                },
                locked: function () {
                    return !!a;
                },
                fireWith: function (e, t) {
                    return a || ((t = [e, (t = t || []).slice ? t.slice() : t]), u.push(t), i || c()), this;
                },
                fire: function () {
                    return f.fireWith(this, arguments), this;
                },
                fired: function () {
                    return !!o;
                },
            };
        return f;
    }),
        E.extend({
            Deferred: function (e) {
                var o = [
                        ["notify", "progress", E.Callbacks("memory"), E.Callbacks("memory"), 2],
                        ["resolve", "done", E.Callbacks("once memory"), E.Callbacks("once memory"), 0, "resolved"],
                        ["reject", "fail", E.Callbacks("once memory"), E.Callbacks("once memory"), 1, "rejected"],
                    ],
                    i = "pending",
                    a = {
                        state: function () {
                            return i;
                        },
                        always: function () {
                            return s.done(arguments).fail(arguments), this;
                        },
                        catch: function (e) {
                            return a.then(null, e);
                        },
                        pipe: function () {
                            var i = arguments;
                            return E.Deferred(function (r) {
                                E.each(o, function (e, t) {
                                    var n = m(i[t[4]]) && i[t[4]];
                                    s[t[1]](function () {
                                        var e = n && n.apply(this, arguments);
                                        e && m(e.promise) ? e.promise().progress(r.notify).done(r.resolve).fail(r.reject) : r[t[0] + "With"](this, n ? [e] : arguments);
                                    });
                                }),
                                    (i = null);
                            }).promise();
                        },
                        then: function (t, n, r) {
                            var u = 0;
                            function l(i, o, a, s) {
                                return function () {
                                    var n = this,
                                        r = arguments,
                                        e = function () {
                                            var e, t;
                                            if (!(i < u)) {
                                                if ((e = a.apply(n, r)) === o.promise()) throw new TypeError("Thenable self-resolution");
                                                (t = e && ("object" == typeof e || "function" == typeof e) && e.then),
                                                    m(t)
                                                        ? s
                                                            ? t.call(e, l(u, o, R, s), l(u, o, M, s))
                                                            : (u++, t.call(e, l(u, o, R, s), l(u, o, M, s), l(u, o, R, o.notifyWith)))
                                                        : (a !== R && ((n = void 0), (r = [e])), (s || o.resolveWith)(n, r));
                                            }
                                        },
                                        t = s
                                            ? e
                                            : function () {
                                                  try {
                                                      e();
                                                  } catch (e) {
                                                      E.Deferred.exceptionHook && E.Deferred.exceptionHook(e, t.stackTrace), u <= i + 1 && (a !== M && ((n = void 0), (r = [e])), o.rejectWith(n, r));
                                                  }
                                              };
                                    i ? t() : (E.Deferred.getStackHook && (t.stackTrace = E.Deferred.getStackHook()), C.setTimeout(t));
                                };
                            }
                            return E.Deferred(function (e) {
                                o[0][3].add(l(0, e, m(r) ? r : R, e.notifyWith)), o[1][3].add(l(0, e, m(t) ? t : R)), o[2][3].add(l(0, e, m(n) ? n : M));
                            }).promise();
                        },
                        promise: function (e) {
                            return null != e ? E.extend(e, a) : a;
                        },
                    },
                    s = {};
                return (
                    E.each(o, function (e, t) {
                        var n = t[2],
                            r = t[5];
                        (a[t[1]] = n.add),
                            r &&
                                n.add(
                                    function () {
                                        i = r;
                                    },
                                    o[3 - e][2].disable,
                                    o[3 - e][3].disable,
                                    o[0][2].lock,
                                    o[0][3].lock
                                ),
                            n.add(t[3].fire),
                            (s[t[0]] = function () {
                                return s[t[0] + "With"](this === s ? void 0 : this, arguments), this;
                            }),
                            (s[t[0] + "With"] = n.fireWith);
                    }),
                    a.promise(s),
                    e && e.call(s, s),
                    s
                );
            },
            when: function (e) {
                var n = arguments.length,
                    t = n,
                    r = Array(t),
                    i = s.call(arguments),
                    o = E.Deferred(),
                    a = function (t) {
                        return function (e) {
                            (r[t] = this), (i[t] = 1 < arguments.length ? s.call(arguments) : e), --n || o.resolveWith(r, i);
                        };
                    };
                if (n <= 1 && (I(e, o.done(a(t)).resolve, o.reject, !n), "pending" === o.state() || m(i[t] && i[t].then))) return o.then();
                while (t--) I(i[t], a(t), o.reject);
                return o.promise();
            },
        });
    var W = /^(Eval|Internal|Range|Reference|Syntax|Type|URI)Error$/;
    (E.Deferred.exceptionHook = function (e, t) {
        C.console && C.console.warn && e && W.test(e.name) && C.console.warn("jQuery.Deferred exception: " + e.message, e.stack, t);
    }),
        (E.readyException = function (e) {
            C.setTimeout(function () {
                throw e;
            });
        });
    var F = E.Deferred();
    function $() {
        S.removeEventListener("DOMContentLoaded", $), C.removeEventListener("load", $), E.ready();
    }
    (E.fn.ready = function (e) {
        return (
            F.then(e)["catch"](function (e) {
                E.readyException(e);
            }),
            this
        );
    }),
        E.extend({
            isReady: !1,
            readyWait: 1,
            ready: function (e) {
                (!0 === e ? --E.readyWait : E.isReady) || ((E.isReady = !0) !== e && 0 < --E.readyWait) || F.resolveWith(S, [E]);
            },
        }),
        (E.ready.then = F.then),
        "complete" === S.readyState || ("loading" !== S.readyState && !S.documentElement.doScroll) ? C.setTimeout(E.ready) : (S.addEventListener("DOMContentLoaded", $), C.addEventListener("load", $));
    var B = function (e, t, n, r, i, o, a) {
            var s = 0,
                u = e.length,
                l = null == n;
            if ("object" === w(n)) for (s in ((i = !0), n)) B(e, t, s, n[s], !0, o, a);
            else if (
                void 0 !== r &&
                ((i = !0),
                m(r) || (a = !0),
                l &&
                    (a
                        ? (t.call(e, r), (t = null))
                        : ((l = t),
                          (t = function (e, t, n) {
                              return l.call(E(e), n);
                          }))),
                t)
            )
                for (; s < u; s++) t(e[s], n, a ? r : r.call(e[s], s, t(e[s], n)));
            return i ? e : l ? t.call(e) : u ? t(e[0], n) : o;
        },
        _ = /^-ms-/,
        z = /-([a-z])/g;
    function U(e, t) {
        return t.toUpperCase();
    }
    function X(e) {
        return e.replace(_, "ms-").replace(z, U);
    }
    var V = function (e) {
        return 1 === e.nodeType || 9 === e.nodeType || !+e.nodeType;
    };
    function G() {
        this.expando = E.expando + G.uid++;
    }
    (G.uid = 1),
        (G.prototype = {
            cache: function (e) {
                var t = e[this.expando];
                return t || ((t = {}), V(e) && (e.nodeType ? (e[this.expando] = t) : Object.defineProperty(e, this.expando, { value: t, configurable: !0 }))), t;
            },
            set: function (e, t, n) {
                var r,
                    i = this.cache(e);
                if ("string" == typeof t) i[X(t)] = n;
                else for (r in t) i[X(r)] = t[r];
                return i;
            },
            get: function (e, t) {
                return void 0 === t ? this.cache(e) : e[this.expando] && e[this.expando][X(t)];
            },
            access: function (e, t, n) {
                return void 0 === t || (t && "string" == typeof t && void 0 === n) ? this.get(e, t) : (this.set(e, t, n), void 0 !== n ? n : t);
            },
            remove: function (e, t) {
                var n,
                    r = e[this.expando];
                if (void 0 !== r) {
                    if (void 0 !== t) {
                        n = (t = Array.isArray(t) ? t.map(X) : (t = X(t)) in r ? [t] : t.match(P) || []).length;
                        while (n--) delete r[t[n]];
                    }
                    (void 0 === t || E.isEmptyObject(r)) && (e.nodeType ? (e[this.expando] = void 0) : delete e[this.expando]);
                }
            },
            hasData: function (e) {
                var t = e[this.expando];
                return void 0 !== t && !E.isEmptyObject(t);
            },
        });
    var Y = new G(),
        Q = new G(),
        J = /^(?:\{[\w\W]*\}|\[[\w\W]*\])$/,
        K = /[A-Z]/g;
    function Z(e, t, n) {
        var r, i;
        if (void 0 === n && 1 === e.nodeType)
            if (((r = "data-" + t.replace(K, "-$&").toLowerCase()), "string" == typeof (n = e.getAttribute(r)))) {
                try {
                    n = "true" === (i = n) || ("false" !== i && ("null" === i ? null : i === +i + "" ? +i : J.test(i) ? JSON.parse(i) : i));
                } catch (e) {}
                Q.set(e, t, n);
            } else n = void 0;
        return n;
    }
    E.extend({
        hasData: function (e) {
            return Q.hasData(e) || Y.hasData(e);
        },
        data: function (e, t, n) {
            return Q.access(e, t, n);
        },
        removeData: function (e, t) {
            Q.remove(e, t);
        },
        _data: function (e, t, n) {
            return Y.access(e, t, n);
        },
        _removeData: function (e, t) {
            Y.remove(e, t);
        },
    }),
        E.fn.extend({
            data: function (n, e) {
                var t,
                    r,
                    i,
                    o = this[0],
                    a = o && o.attributes;
                if (void 0 === n) {
                    if (this.length && ((i = Q.get(o)), 1 === o.nodeType && !Y.get(o, "hasDataAttrs"))) {
                        t = a.length;
                        while (t--) a[t] && 0 === (r = a[t].name).indexOf("data-") && ((r = X(r.slice(5))), Z(o, r, i[r]));
                        Y.set(o, "hasDataAttrs", !0);
                    }
                    return i;
                }
                return "object" == typeof n
                    ? this.each(function () {
                          Q.set(this, n);
                      })
                    : B(
                          this,
                          function (e) {
                              var t;
                              if (o && void 0 === e) return void 0 !== (t = Q.get(o, n)) ? t : void 0 !== (t = Z(o, n)) ? t : void 0;
                              this.each(function () {
                                  Q.set(this, n, e);
                              });
                          },
                          null,
                          e,
                          1 < arguments.length,
                          null,
                          !0
                      );
            },
            removeData: function (e) {
                return this.each(function () {
                    Q.remove(this, e);
                });
            },
        }),
        E.extend({
            queue: function (e, t, n) {
                var r;
                if (e) return (t = (t || "fx") + "queue"), (r = Y.get(e, t)), n && (!r || Array.isArray(n) ? (r = Y.access(e, t, E.makeArray(n))) : r.push(n)), r || [];
            },
            dequeue: function (e, t) {
                t = t || "fx";
                var n = E.queue(e, t),
                    r = n.length,
                    i = n.shift(),
                    o = E._queueHooks(e, t);
                "inprogress" === i && ((i = n.shift()), r--),
                    i &&
                        ("fx" === t && n.unshift("inprogress"),
                        delete o.stop,
                        i.call(
                            e,
                            function () {
                                E.dequeue(e, t);
                            },
                            o
                        )),
                    !r && o && o.empty.fire();
            },
            _queueHooks: function (e, t) {
                var n = t + "queueHooks";
                return (
                    Y.get(e, n) ||
                    Y.access(e, n, {
                        empty: E.Callbacks("once memory").add(function () {
                            Y.remove(e, [t + "queue", n]);
                        }),
                    })
                );
            },
        }),
        E.fn.extend({
            queue: function (t, n) {
                var e = 2;
                return (
                    "string" != typeof t && ((n = t), (t = "fx"), e--),
                    arguments.length < e
                        ? E.queue(this[0], t)
                        : void 0 === n
                        ? this
                        : this.each(function () {
                              var e = E.queue(this, t, n);
                              E._queueHooks(this, t), "fx" === t && "inprogress" !== e[0] && E.dequeue(this, t);
                          })
                );
            },
            dequeue: function (e) {
                return this.each(function () {
                    E.dequeue(this, e);
                });
            },
            clearQueue: function (e) {
                return this.queue(e || "fx", []);
            },
            promise: function (e, t) {
                var n,
                    r = 1,
                    i = E.Deferred(),
                    o = this,
                    a = this.length,
                    s = function () {
                        --r || i.resolveWith(o, [o]);
                    };
                "string" != typeof e && ((t = e), (e = void 0)), (e = e || "fx");
                while (a--) (n = Y.get(o[a], e + "queueHooks")) && n.empty && (r++, n.empty.add(s));
                return s(), i.promise(t);
            },
        });
    var ee = /[+-]?(?:\d*\.|)\d+(?:[eE][+-]?\d+|)/.source,
        te = new RegExp("^(?:([+-])=|)(" + ee + ")([a-z%]*)$", "i"),
        ne = ["Top", "Right", "Bottom", "Left"],
        re = S.documentElement,
        ie = function (e) {
            return E.contains(e.ownerDocument, e);
        },
        oe = { composed: !0 };
    re.getRootNode &&
        (ie = function (e) {
            return E.contains(e.ownerDocument, e) || e.getRootNode(oe) === e.ownerDocument;
        });
    var ae = function (e, t) {
        return "none" === (e = t || e).style.display || ("" === e.style.display && ie(e) && "none" === E.css(e, "display"));
    };
    function se(e, t, n, r) {
        var i,
            o,
            a = 20,
            s = r
                ? function () {
                      return r.cur();
                  }
                : function () {
                      return E.css(e, t, "");
                  },
            u = s(),
            l = (n && n[3]) || (E.cssNumber[t] ? "" : "px"),
            c = e.nodeType && (E.cssNumber[t] || ("px" !== l && +u)) && te.exec(E.css(e, t));
        if (c && c[3] !== l) {
            (u /= 2), (l = l || c[3]), (c = +u || 1);
            while (a--) E.style(e, t, c + l), (1 - o) * (1 - (o = s() / u || 0.5)) <= 0 && (a = 0), (c /= o);
            (c *= 2), E.style(e, t, c + l), (n = n || []);
        }
        return n && ((c = +c || +u || 0), (i = n[1] ? c + (n[1] + 1) * n[2] : +n[2]), r && ((r.unit = l), (r.start = c), (r.end = i))), i;
    }
    var ue = {};
    function le(e, t) {
        for (var n, r, i, o, a, s, u, l = [], c = 0, f = e.length; c < f; c++)
            (r = e[c]).style &&
                ((n = r.style.display),
                t
                    ? ("none" === n && ((l[c] = Y.get(r, "display") || null), l[c] || (r.style.display = "")),
                      "" === r.style.display &&
                          ae(r) &&
                          (l[c] =
                              ((u = a = o = void 0),
                              (a = (i = r).ownerDocument),
                              (s = i.nodeName),
                              (u = ue[s]) || ((o = a.body.appendChild(a.createElement(s))), (u = E.css(o, "display")), o.parentNode.removeChild(o), "none" === u && (u = "block"), (ue[s] = u)))))
                    : "none" !== n && ((l[c] = "none"), Y.set(r, "display", n)));
        for (c = 0; c < f; c++) null != l[c] && (e[c].style.display = l[c]);
        return e;
    }
    E.fn.extend({
        show: function () {
            return le(this, !0);
        },
        hide: function () {
            return le(this);
        },
        toggle: function (e) {
            return "boolean" == typeof e
                ? e
                    ? this.show()
                    : this.hide()
                : this.each(function () {
                      ae(this) ? E(this).show() : E(this).hide();
                  });
        },
    });
    var ce,
        fe,
        pe = /^(?:checkbox|radio)$/i,
        de = /<([a-z][^\/\0>\x20\t\r\n\f]*)/i,
        he = /^$|^module$|\/(?:java|ecma)script/i;
    (ce = S.createDocumentFragment().appendChild(S.createElement("div"))),
        (fe = S.createElement("input")).setAttribute("type", "radio"),
        fe.setAttribute("checked", "checked"),
        fe.setAttribute("name", "t"),
        ce.appendChild(fe),
        (v.checkClone = ce.cloneNode(!0).cloneNode(!0).lastChild.checked),
        (ce.innerHTML = "<textarea>x</textarea>"),
        (v.noCloneChecked = !!ce.cloneNode(!0).lastChild.defaultValue),
        (ce.innerHTML = "<option></option>"),
        (v.option = !!ce.lastChild);
    var ge = { thead: [1, "<table>", "</table>"], col: [2, "<table><colgroup>", "</colgroup></table>"], tr: [2, "<table><tbody>", "</tbody></table>"], td: [3, "<table><tbody><tr>", "</tr></tbody></table>"], _default: [0, "", ""] };
    function ye(e, t) {
        var n;
        return (n = "undefined" != typeof e.getElementsByTagName ? e.getElementsByTagName(t || "*") : "undefined" != typeof e.querySelectorAll ? e.querySelectorAll(t || "*") : []), void 0 === t || (t && A(e, t)) ? E.merge([e], n) : n;
    }
    function ve(e, t) {
        for (var n = 0, r = e.length; n < r; n++) Y.set(e[n], "globalEval", !t || Y.get(t[n], "globalEval"));
    }
    (ge.tbody = ge.tfoot = ge.colgroup = ge.caption = ge.thead), (ge.th = ge.td), v.option || (ge.optgroup = ge.option = [1, "<select multiple='multiple'>", "</select>"]);
    var me = /<|&#?\w+;/;
    function xe(e, t, n, r, i) {
        for (var o, a, s, u, l, c, f = t.createDocumentFragment(), p = [], d = 0, h = e.length; d < h; d++)
            if ((o = e[d]) || 0 === o)
                if ("object" === w(o)) E.merge(p, o.nodeType ? [o] : o);
                else if (me.test(o)) {
                    (a = a || f.appendChild(t.createElement("div"))), (s = (de.exec(o) || ["", ""])[1].toLowerCase()), (u = ge[s] || ge._default), (a.innerHTML = u[1] + E.htmlPrefilter(o) + u[2]), (c = u[0]);
                    while (c--) a = a.lastChild;
                    E.merge(p, a.childNodes), ((a = f.firstChild).textContent = "");
                } else p.push(t.createTextNode(o));
        (f.textContent = ""), (d = 0);
        while ((o = p[d++]))
            if (r && -1 < E.inArray(o, r)) i && i.push(o);
            else if (((l = ie(o)), (a = ye(f.appendChild(o), "script")), l && ve(a), n)) {
                c = 0;
                while ((o = a[c++])) he.test(o.type || "") && n.push(o);
            }
        return f;
    }
    var be = /^([^.]*)(?:\.(.+)|)/;
    function we() {
        return !0;
    }
    function Te() {
        return !1;
    }
    function Ce(e, t) {
        return (
            (e ===
                (function () {
                    try {
                        return S.activeElement;
                    } catch (e) {}
                })()) ==
            ("focus" === t)
        );
    }
    function Se(e, t, n, r, i, o) {
        var a, s;
        if ("object" == typeof t) {
            for (s in ("string" != typeof n && ((r = r || n), (n = void 0)), t)) Se(e, s, n, r, t[s], o);
            return e;
        }
        if ((null == r && null == i ? ((i = n), (r = n = void 0)) : null == i && ("string" == typeof n ? ((i = r), (r = void 0)) : ((i = r), (r = n), (n = void 0))), !1 === i)) i = Te;
        else if (!i) return e;
        return (
            1 === o &&
                ((a = i),
                ((i = function (e) {
                    return E().off(e), a.apply(this, arguments);
                }).guid = a.guid || (a.guid = E.guid++))),
            e.each(function () {
                E.event.add(this, t, i, r, n);
            })
        );
    }
    function Ee(e, i, o) {
        o
            ? (Y.set(e, i, !1),
              E.event.add(e, i, {
                  namespace: !1,
                  handler: function (e) {
                      var t,
                          n,
                          r = Y.get(this, i);
                      if (1 & e.isTrigger && this[i]) {
                          if (r.length) (E.event.special[i] || {}).delegateType && e.stopPropagation();
                          else if (((r = s.call(arguments)), Y.set(this, i, r), (t = o(this, i)), this[i](), r !== (n = Y.get(this, i)) || t ? Y.set(this, i, !1) : (n = {}), r !== n))
                              return e.stopImmediatePropagation(), e.preventDefault(), n && n.value;
                      } else r.length && (Y.set(this, i, { value: E.event.trigger(E.extend(r[0], E.Event.prototype), r.slice(1), this) }), e.stopImmediatePropagation());
                  },
              }))
            : void 0 === Y.get(e, i) && E.event.add(e, i, we);
    }
    (E.event = {
        global: {},
        add: function (t, e, n, r, i) {
            var o,
                a,
                s,
                u,
                l,
                c,
                f,
                p,
                d,
                h,
                g,
                y = Y.get(t);
            if (V(t)) {
                n.handler && ((n = (o = n).handler), (i = o.selector)),
                    i && E.find.matchesSelector(re, i),
                    n.guid || (n.guid = E.guid++),
                    (u = y.events) || (u = y.events = Object.create(null)),
                    (a = y.handle) ||
                        (a = y.handle = function (e) {
                            return "undefined" != typeof E && E.event.triggered !== e.type ? E.event.dispatch.apply(t, arguments) : void 0;
                        }),
                    (l = (e = (e || "").match(P) || [""]).length);
                while (l--)
                    (d = g = (s = be.exec(e[l]) || [])[1]),
                        (h = (s[2] || "").split(".").sort()),
                        d &&
                            ((f = E.event.special[d] || {}),
                            (d = (i ? f.delegateType : f.bindType) || d),
                            (f = E.event.special[d] || {}),
                            (c = E.extend({ type: d, origType: g, data: r, handler: n, guid: n.guid, selector: i, needsContext: i && E.expr.match.needsContext.test(i), namespace: h.join(".") }, o)),
                            (p = u[d]) || (((p = u[d] = []).delegateCount = 0), (f.setup && !1 !== f.setup.call(t, r, h, a)) || (t.addEventListener && t.addEventListener(d, a))),
                            f.add && (f.add.call(t, c), c.handler.guid || (c.handler.guid = n.guid)),
                            i ? p.splice(p.delegateCount++, 0, c) : p.push(c),
                            (E.event.global[d] = !0));
            }
        },
        remove: function (e, t, n, r, i) {
            var o,
                a,
                s,
                u,
                l,
                c,
                f,
                p,
                d,
                h,
                g,
                y = Y.hasData(e) && Y.get(e);
            if (y && (u = y.events)) {
                l = (t = (t || "").match(P) || [""]).length;
                while (l--)
                    if (((d = g = (s = be.exec(t[l]) || [])[1]), (h = (s[2] || "").split(".").sort()), d)) {
                        (f = E.event.special[d] || {}), (p = u[(d = (r ? f.delegateType : f.bindType) || d)] || []), (s = s[2] && new RegExp("(^|\\.)" + h.join("\\.(?:.*\\.|)") + "(\\.|$)")), (a = o = p.length);
                        while (o--)
                            (c = p[o]),
                                (!i && g !== c.origType) ||
                                    (n && n.guid !== c.guid) ||
                                    (s && !s.test(c.namespace)) ||
                                    (r && r !== c.selector && ("**" !== r || !c.selector)) ||
                                    (p.splice(o, 1), c.selector && p.delegateCount--, f.remove && f.remove.call(e, c));
                        a && !p.length && ((f.teardown && !1 !== f.teardown.call(e, h, y.handle)) || E.removeEvent(e, d, y.handle), delete u[d]);
                    } else for (d in u) E.event.remove(e, d + t[l], n, r, !0);
                E.isEmptyObject(u) && Y.remove(e, "handle events");
            }
        },
        dispatch: function (e) {
            var t,
                n,
                r,
                i,
                o,
                a,
                s = new Array(arguments.length),
                u = E.event.fix(e),
                l = (Y.get(this, "events") || Object.create(null))[u.type] || [],
                c = E.event.special[u.type] || {};
            for (s[0] = u, t = 1; t < arguments.length; t++) s[t] = arguments[t];
            if (((u.delegateTarget = this), !c.preDispatch || !1 !== c.preDispatch.call(this, u))) {
                (a = E.event.handlers.call(this, u, l)), (t = 0);
                while ((i = a[t++]) && !u.isPropagationStopped()) {
                    (u.currentTarget = i.elem), (n = 0);
                    while ((o = i.handlers[n++]) && !u.isImmediatePropagationStopped())
                        (u.rnamespace && !1 !== o.namespace && !u.rnamespace.test(o.namespace)) ||
                            ((u.handleObj = o), (u.data = o.data), void 0 !== (r = ((E.event.special[o.origType] || {}).handle || o.handler).apply(i.elem, s)) && !1 === (u.result = r) && (u.preventDefault(), u.stopPropagation()));
                }
                return c.postDispatch && c.postDispatch.call(this, u), u.result;
            }
        },
        handlers: function (e, t) {
            var n,
                r,
                i,
                o,
                a,
                s = [],
                u = t.delegateCount,
                l = e.target;
            if (u && l.nodeType && !("click" === e.type && 1 <= e.button))
                for (; l !== this; l = l.parentNode || this)
                    if (1 === l.nodeType && ("click" !== e.type || !0 !== l.disabled)) {
                        for (o = [], a = {}, n = 0; n < u; n++) void 0 === a[(i = (r = t[n]).selector + " ")] && (a[i] = r.needsContext ? -1 < E(i, this).index(l) : E.find(i, this, null, [l]).length), a[i] && o.push(r);
                        o.length && s.push({ elem: l, handlers: o });
                    }
            return (l = this), u < t.length && s.push({ elem: l, handlers: t.slice(u) }), s;
        },
        addProp: function (t, e) {
            Object.defineProperty(E.Event.prototype, t, {
                enumerable: !0,
                configurable: !0,
                get: m(e)
                    ? function () {
                          if (this.originalEvent) return e(this.originalEvent);
                      }
                    : function () {
                          if (this.originalEvent) return this.originalEvent[t];
                      },
                set: function (e) {
                    Object.defineProperty(this, t, { enumerable: !0, configurable: !0, writable: !0, value: e });
                },
            });
        },
        fix: function (e) {
            return e[E.expando] ? e : new E.Event(e);
        },
        special: {
            load: { noBubble: !0 },
            click: {
                setup: function (e) {
                    var t = this || e;
                    return pe.test(t.type) && t.click && A(t, "input") && Ee(t, "click", we), !1;
                },
                trigger: function (e) {
                    var t = this || e;
                    return pe.test(t.type) && t.click && A(t, "input") && Ee(t, "click"), !0;
                },
                _default: function (e) {
                    var t = e.target;
                    return (pe.test(t.type) && t.click && A(t, "input") && Y.get(t, "click")) || A(t, "a");
                },
            },
            beforeunload: {
                postDispatch: function (e) {
                    void 0 !== e.result && e.originalEvent && (e.originalEvent.returnValue = e.result);
                },
            },
        },
    }),
        (E.removeEvent = function (e, t, n) {
            e.removeEventListener && e.removeEventListener(t, n);
        }),
        (E.Event = function (e, t) {
            if (!(this instanceof E.Event)) return new E.Event(e, t);
            e && e.type
                ? ((this.originalEvent = e),
                  (this.type = e.type),
                  (this.isDefaultPrevented = e.defaultPrevented || (void 0 === e.defaultPrevented && !1 === e.returnValue) ? we : Te),
                  (this.target = e.target && 3 === e.target.nodeType ? e.target.parentNode : e.target),
                  (this.currentTarget = e.currentTarget),
                  (this.relatedTarget = e.relatedTarget))
                : (this.type = e),
                t && E.extend(this, t),
                (this.timeStamp = (e && e.timeStamp) || Date.now()),
                (this[E.expando] = !0);
        }),
        (E.Event.prototype = {
            constructor: E.Event,
            isDefaultPrevented: Te,
            isPropagationStopped: Te,
            isImmediatePropagationStopped: Te,
            isSimulated: !1,
            preventDefault: function () {
                var e = this.originalEvent;
                (this.isDefaultPrevented = we), e && !this.isSimulated && e.preventDefault();
            },
            stopPropagation: function () {
                var e = this.originalEvent;
                (this.isPropagationStopped = we), e && !this.isSimulated && e.stopPropagation();
            },
            stopImmediatePropagation: function () {
                var e = this.originalEvent;
                (this.isImmediatePropagationStopped = we), e && !this.isSimulated && e.stopImmediatePropagation(), this.stopPropagation();
            },
        }),
        E.each(
            {
                altKey: !0,
                bubbles: !0,
                cancelable: !0,
                changedTouches: !0,
                ctrlKey: !0,
                detail: !0,
                eventPhase: !0,
                metaKey: !0,
                pageX: !0,
                pageY: !0,
                shiftKey: !0,
                view: !0,
                char: !0,
                code: !0,
                charCode: !0,
                key: !0,
                keyCode: !0,
                button: !0,
                buttons: !0,
                clientX: !0,
                clientY: !0,
                offsetX: !0,
                offsetY: !0,
                pointerId: !0,
                pointerType: !0,
                screenX: !0,
                screenY: !0,
                targetTouches: !0,
                toElement: !0,
                touches: !0,
                which: !0,
            },
            E.event.addProp
        ),
        E.each({ focus: "focusin", blur: "focusout" }, function (t, e) {
            E.event.special[t] = {
                setup: function () {
                    return Ee(this, t, Ce), !1;
                },
                trigger: function () {
                    return Ee(this, t), !0;
                },
                _default: function (e) {
                    return Y.get(e.target, t);
                },
                delegateType: e,
            };
        }),
        E.each({ mouseenter: "mouseover", mouseleave: "mouseout", pointerenter: "pointerover", pointerleave: "pointerout" }, function (e, i) {
            E.event.special[e] = {
                delegateType: i,
                bindType: i,
                handle: function (e) {
                    var t,
                        n = e.relatedTarget,
                        r = e.handleObj;
                    return (n && (n === this || E.contains(this, n))) || ((e.type = r.origType), (t = r.handler.apply(this, arguments)), (e.type = i)), t;
                },
            };
        }),
        E.fn.extend({
            on: function (e, t, n, r) {
                return Se(this, e, t, n, r);
            },
            one: function (e, t, n, r) {
                return Se(this, e, t, n, r, 1);
            },
            off: function (e, t, n) {
                var r, i;
                if (e && e.preventDefault && e.handleObj) return (r = e.handleObj), E(e.delegateTarget).off(r.namespace ? r.origType + "." + r.namespace : r.origType, r.selector, r.handler), this;
                if ("object" == typeof e) {
                    for (i in e) this.off(i, t, e[i]);
                    return this;
                }
                return (
                    (!1 !== t && "function" != typeof t) || ((n = t), (t = void 0)),
                    !1 === n && (n = Te),
                    this.each(function () {
                        E.event.remove(this, e, n, t);
                    })
                );
            },
        });
    var ke = /<script|<style|<link/i,
        Ae = /checked\s*(?:[^=]|=\s*.checked.)/i,
        Ne = /^\s*<!\[CDATA\[|\]\]>\s*$/g;
    function je(e, t) {
        return (A(e, "table") && A(11 !== t.nodeType ? t : t.firstChild, "tr") && E(e).children("tbody")[0]) || e;
    }
    function De(e) {
        return (e.type = (null !== e.getAttribute("type")) + "/" + e.type), e;
    }
    function qe(e) {
        return "true/" === (e.type || "").slice(0, 5) ? (e.type = e.type.slice(5)) : e.removeAttribute("type"), e;
    }
    function Le(e, t) {
        var n, r, i, o, a, s;
        if (1 === t.nodeType) {
            if (Y.hasData(e) && (s = Y.get(e).events)) for (i in (Y.remove(t, "handle events"), s)) for (n = 0, r = s[i].length; n < r; n++) E.event.add(t, i, s[i][n]);
            Q.hasData(e) && ((o = Q.access(e)), (a = E.extend({}, o)), Q.set(t, a));
        }
    }
    function He(n, r, i, o) {
        r = g(r);
        var e,
            t,
            a,
            s,
            u,
            l,
            c = 0,
            f = n.length,
            p = f - 1,
            d = r[0],
            h = m(d);
        if (h || (1 < f && "string" == typeof d && !v.checkClone && Ae.test(d)))
            return n.each(function (e) {
                var t = n.eq(e);
                h && (r[0] = d.call(this, e, t.html())), He(t, r, i, o);
            });
        if (f && ((t = (e = xe(r, n[0].ownerDocument, !1, n, o)).firstChild), 1 === e.childNodes.length && (e = t), t || o)) {
            for (s = (a = E.map(ye(e, "script"), De)).length; c < f; c++) (u = e), c !== p && ((u = E.clone(u, !0, !0)), s && E.merge(a, ye(u, "script"))), i.call(n[c], u, c);
            if (s)
                for (l = a[a.length - 1].ownerDocument, E.map(a, qe), c = 0; c < s; c++)
                    (u = a[c]),
                        he.test(u.type || "") &&
                            !Y.access(u, "globalEval") &&
                            E.contains(l, u) &&
                            (u.src && "module" !== (u.type || "").toLowerCase() ? E._evalUrl && !u.noModule && E._evalUrl(u.src, { nonce: u.nonce || u.getAttribute("nonce") }, l) : b(u.textContent.replace(Ne, ""), u, l));
        }
        return n;
    }
    function Oe(e, t, n) {
        for (var r, i = t ? E.filter(t, e) : e, o = 0; null != (r = i[o]); o++) n || 1 !== r.nodeType || E.cleanData(ye(r)), r.parentNode && (n && ie(r) && ve(ye(r, "script")), r.parentNode.removeChild(r));
        return e;
    }
    E.extend({
        htmlPrefilter: function (e) {
            return e;
        },
        clone: function (e, t, n) {
            var r,
                i,
                o,
                a,
                s,
                u,
                l,
                c = e.cloneNode(!0),
                f = ie(e);
            if (!(v.noCloneChecked || (1 !== e.nodeType && 11 !== e.nodeType) || E.isXMLDoc(e)))
                for (a = ye(c), r = 0, i = (o = ye(e)).length; r < i; r++)
                    (s = o[r]), (u = a[r]), void 0, "input" === (l = u.nodeName.toLowerCase()) && pe.test(s.type) ? (u.checked = s.checked) : ("input" !== l && "textarea" !== l) || (u.defaultValue = s.defaultValue);
            if (t)
                if (n) for (o = o || ye(e), a = a || ye(c), r = 0, i = o.length; r < i; r++) Le(o[r], a[r]);
                else Le(e, c);
            return 0 < (a = ye(c, "script")).length && ve(a, !f && ye(e, "script")), c;
        },
        cleanData: function (e) {
            for (var t, n, r, i = E.event.special, o = 0; void 0 !== (n = e[o]); o++)
                if (V(n)) {
                    if ((t = n[Y.expando])) {
                        if (t.events) for (r in t.events) i[r] ? E.event.remove(n, r) : E.removeEvent(n, r, t.handle);
                        n[Y.expando] = void 0;
                    }
                    n[Q.expando] && (n[Q.expando] = void 0);
                }
        },
    }),
        E.fn.extend({
            detach: function (e) {
                return Oe(this, e, !0);
            },
            remove: function (e) {
                return Oe(this, e);
            },
            text: function (e) {
                return B(
                    this,
                    function (e) {
                        return void 0 === e
                            ? E.text(this)
                            : this.empty().each(function () {
                                  (1 !== this.nodeType && 11 !== this.nodeType && 9 !== this.nodeType) || (this.textContent = e);
                              });
                    },
                    null,
                    e,
                    arguments.length
                );
            },
            append: function () {
                return He(this, arguments, function (e) {
                    (1 !== this.nodeType && 11 !== this.nodeType && 9 !== this.nodeType) || je(this, e).appendChild(e);
                });
            },
            prepend: function () {
                return He(this, arguments, function (e) {
                    if (1 === this.nodeType || 11 === this.nodeType || 9 === this.nodeType) {
                        var t = je(this, e);
                        t.insertBefore(e, t.firstChild);
                    }
                });
            },
            before: function () {
                return He(this, arguments, function (e) {
                    this.parentNode && this.parentNode.insertBefore(e, this);
                });
            },
            after: function () {
                return He(this, arguments, function (e) {
                    this.parentNode && this.parentNode.insertBefore(e, this.nextSibling);
                });
            },
            empty: function () {
                for (var e, t = 0; null != (e = this[t]); t++) 1 === e.nodeType && (E.cleanData(ye(e, !1)), (e.textContent = ""));
                return this;
            },
            clone: function (e, t) {
                return (
                    (e = null != e && e),
                    (t = null == t ? e : t),
                    this.map(function () {
                        return E.clone(this, e, t);
                    })
                );
            },
            html: function (e) {
                return B(
                    this,
                    function (e) {
                        var t = this[0] || {},
                            n = 0,
                            r = this.length;
                        if (void 0 === e && 1 === t.nodeType) return t.innerHTML;
                        if ("string" == typeof e && !ke.test(e) && !ge[(de.exec(e) || ["", ""])[1].toLowerCase()]) {
                            e = E.htmlPrefilter(e);
                            try {
                                for (; n < r; n++) 1 === (t = this[n] || {}).nodeType && (E.cleanData(ye(t, !1)), (t.innerHTML = e));
                                t = 0;
                            } catch (e) {}
                        }
                        t && this.empty().append(e);
                    },
                    null,
                    e,
                    arguments.length
                );
            },
            replaceWith: function () {
                var n = [];
                return He(
                    this,
                    arguments,
                    function (e) {
                        var t = this.parentNode;
                        E.inArray(this, n) < 0 && (E.cleanData(ye(this)), t && t.replaceChild(e, this));
                    },
                    n
                );
            },
        }),
        E.each({ appendTo: "append", prependTo: "prepend", insertBefore: "before", insertAfter: "after", replaceAll: "replaceWith" }, function (e, a) {
            E.fn[e] = function (e) {
                for (var t, n = [], r = E(e), i = r.length - 1, o = 0; o <= i; o++) (t = o === i ? this : this.clone(!0)), E(r[o])[a](t), u.apply(n, t.get());
                return this.pushStack(n);
            };
        });
    var Pe = new RegExp("^(" + ee + ")(?!px)[a-z%]+$", "i"),
        Re = /^--/,
        Me = function (e) {
            var t = e.ownerDocument.defaultView;
            return (t && t.opener) || (t = C), t.getComputedStyle(e);
        },
        Ie = function (e, t, n) {
            var r,
                i,
                o = {};
            for (i in t) (o[i] = e.style[i]), (e.style[i] = t[i]);
            for (i in ((r = n.call(e)), t)) e.style[i] = o[i];
            return r;
        },
        We = new RegExp(ne.join("|"), "i"),
        Fe = "[\\x20\\t\\r\\n\\f]",
        $e = new RegExp("^" + Fe + "+|((?:^|[^\\\\])(?:\\\\.)*)" + Fe + "+$", "g");
    function Be(e, t, n) {
        var r,
            i,
            o,
            a,
            s = Re.test(t),
            u = e.style;
        return (
            (n = n || Me(e)) &&
                ((a = n.getPropertyValue(t) || n[t]),
                s && a && (a = a.replace($e, "$1") || void 0),
                "" !== a || ie(e) || (a = E.style(e, t)),
                !v.pixelBoxStyles() && Pe.test(a) && We.test(t) && ((r = u.width), (i = u.minWidth), (o = u.maxWidth), (u.minWidth = u.maxWidth = u.width = a), (a = n.width), (u.width = r), (u.minWidth = i), (u.maxWidth = o))),
            void 0 !== a ? a + "" : a
        );
    }
    function _e(e, t) {
        return {
            get: function () {
                if (!e()) return (this.get = t).apply(this, arguments);
                delete this.get;
            },
        };
    }
    !(function () {
        function e() {
            if (l) {
                (u.style.cssText = "position:absolute;left:-11111px;width:60px;margin-top:1px;padding:0;border:0"),
                    (l.style.cssText = "position:relative;display:block;box-sizing:border-box;overflow:scroll;margin:auto;border:1px;padding:1px;width:60%;top:1%"),
                    re.appendChild(u).appendChild(l);
                var e = C.getComputedStyle(l);
                (n = "1%" !== e.top),
                    (s = 12 === t(e.marginLeft)),
                    (l.style.right = "60%"),
                    (o = 36 === t(e.right)),
                    (r = 36 === t(e.width)),
                    (l.style.position = "absolute"),
                    (i = 12 === t(l.offsetWidth / 3)),
                    re.removeChild(u),
                    (l = null);
            }
        }
        function t(e) {
            return Math.round(parseFloat(e));
        }
        var n,
            r,
            i,
            o,
            a,
            s,
            u = S.createElement("div"),
            l = S.createElement("div");
        l.style &&
            ((l.style.backgroundClip = "content-box"),
            (l.cloneNode(!0).style.backgroundClip = ""),
            (v.clearCloneStyle = "content-box" === l.style.backgroundClip),
            E.extend(v, {
                boxSizingReliable: function () {
                    return e(), r;
                },
                pixelBoxStyles: function () {
                    return e(), o;
                },
                pixelPosition: function () {
                    return e(), n;
                },
                reliableMarginLeft: function () {
                    return e(), s;
                },
                scrollboxSize: function () {
                    return e(), i;
                },
                reliableTrDimensions: function () {
                    var e, t, n, r;
                    return (
                        null == a &&
                            ((e = S.createElement("table")),
                            (t = S.createElement("tr")),
                            (n = S.createElement("div")),
                            (e.style.cssText = "position:absolute;left:-11111px;border-collapse:separate"),
                            (t.style.cssText = "border:1px solid"),
                            (t.style.height = "1px"),
                            (n.style.height = "9px"),
                            (n.style.display = "block"),
                            re.appendChild(e).appendChild(t).appendChild(n),
                            (r = C.getComputedStyle(t)),
                            (a = parseInt(r.height, 10) + parseInt(r.borderTopWidth, 10) + parseInt(r.borderBottomWidth, 10) === t.offsetHeight),
                            re.removeChild(e)),
                        a
                    );
                },
            }));
    })();
    var ze = ["Webkit", "Moz", "ms"],
        Ue = S.createElement("div").style,
        Xe = {};
    function Ve(e) {
        var t = E.cssProps[e] || Xe[e];
        return (
            t ||
            (e in Ue
                ? e
                : (Xe[e] =
                      (function (e) {
                          var t = e[0].toUpperCase() + e.slice(1),
                              n = ze.length;
                          while (n--) if ((e = ze[n] + t) in Ue) return e;
                      })(e) || e))
        );
    }
    var Ge = /^(none|table(?!-c[ea]).+)/,
        Ye = { position: "absolute", visibility: "hidden", display: "block" },
        Qe = { letterSpacing: "0", fontWeight: "400" };
    function Je(e, t, n) {
        var r = te.exec(t);
        return r ? Math.max(0, r[2] - (n || 0)) + (r[3] || "px") : t;
    }
    function Ke(e, t, n, r, i, o) {
        var a = "width" === t ? 1 : 0,
            s = 0,
            u = 0;
        if (n === (r ? "border" : "content")) return 0;
        for (; a < 4; a += 2)
            "margin" === n && (u += E.css(e, n + ne[a], !0, i)),
                r
                    ? ("content" === n && (u -= E.css(e, "padding" + ne[a], !0, i)), "margin" !== n && (u -= E.css(e, "border" + ne[a] + "Width", !0, i)))
                    : ((u += E.css(e, "padding" + ne[a], !0, i)), "padding" !== n ? (u += E.css(e, "border" + ne[a] + "Width", !0, i)) : (s += E.css(e, "border" + ne[a] + "Width", !0, i)));
        return !r && 0 <= o && (u += Math.max(0, Math.ceil(e["offset" + t[0].toUpperCase() + t.slice(1)] - o - u - s - 0.5)) || 0), u;
    }
    function Ze(e, t, n) {
        var r = Me(e),
            i = (!v.boxSizingReliable() || n) && "border-box" === E.css(e, "boxSizing", !1, r),
            o = i,
            a = Be(e, t, r),
            s = "offset" + t[0].toUpperCase() + t.slice(1);
        if (Pe.test(a)) {
            if (!n) return a;
            a = "auto";
        }
        return (
            ((!v.boxSizingReliable() && i) || (!v.reliableTrDimensions() && A(e, "tr")) || "auto" === a || (!parseFloat(a) && "inline" === E.css(e, "display", !1, r))) &&
                e.getClientRects().length &&
                ((i = "border-box" === E.css(e, "boxSizing", !1, r)), (o = s in e) && (a = e[s])),
            (a = parseFloat(a) || 0) + Ke(e, t, n || (i ? "border" : "content"), o, r, a) + "px"
        );
    }
    function et(e, t, n, r, i) {
        return new et.prototype.init(e, t, n, r, i);
    }
    E.extend({
        cssHooks: {
            opacity: {
                get: function (e, t) {
                    if (t) {
                        var n = Be(e, "opacity");
                        return "" === n ? "1" : n;
                    }
                },
            },
        },
        cssNumber: {
            animationIterationCount: !0,
            columnCount: !0,
            fillOpacity: !0,
            flexGrow: !0,
            flexShrink: !0,
            fontWeight: !0,
            gridArea: !0,
            gridColumn: !0,
            gridColumnEnd: !0,
            gridColumnStart: !0,
            gridRow: !0,
            gridRowEnd: !0,
            gridRowStart: !0,
            lineHeight: !0,
            opacity: !0,
            order: !0,
            orphans: !0,
            widows: !0,
            zIndex: !0,
            zoom: !0,
        },
        cssProps: {},
        style: function (e, t, n, r) {
            if (e && 3 !== e.nodeType && 8 !== e.nodeType && e.style) {
                var i,
                    o,
                    a,
                    s = X(t),
                    u = Re.test(t),
                    l = e.style;
                if ((u || (t = Ve(s)), (a = E.cssHooks[t] || E.cssHooks[s]), void 0 === n)) return a && "get" in a && void 0 !== (i = a.get(e, !1, r)) ? i : l[t];
                "string" === (o = typeof n) && (i = te.exec(n)) && i[1] && ((n = se(e, t, i)), (o = "number")),
                    null != n &&
                        n == n &&
                        ("number" !== o || u || (n += (i && i[3]) || (E.cssNumber[s] ? "" : "px")),
                        v.clearCloneStyle || "" !== n || 0 !== t.indexOf("background") || (l[t] = "inherit"),
                        (a && "set" in a && void 0 === (n = a.set(e, n, r))) || (u ? l.setProperty(t, n) : (l[t] = n)));
            }
        },
        css: function (e, t, n, r) {
            var i,
                o,
                a,
                s = X(t);
            return (
                Re.test(t) || (t = Ve(s)),
                (a = E.cssHooks[t] || E.cssHooks[s]) && "get" in a && (i = a.get(e, !0, n)),
                void 0 === i && (i = Be(e, t, r)),
                "normal" === i && t in Qe && (i = Qe[t]),
                "" === n || n ? ((o = parseFloat(i)), !0 === n || isFinite(o) ? o || 0 : i) : i
            );
        },
    }),
        E.each(["height", "width"], function (e, u) {
            E.cssHooks[u] = {
                get: function (e, t, n) {
                    if (t)
                        return !Ge.test(E.css(e, "display")) || (e.getClientRects().length && e.getBoundingClientRect().width)
                            ? Ze(e, u, n)
                            : Ie(e, Ye, function () {
                                  return Ze(e, u, n);
                              });
                },
                set: function (e, t, n) {
                    var r,
                        i = Me(e),
                        o = !v.scrollboxSize() && "absolute" === i.position,
                        a = (o || n) && "border-box" === E.css(e, "boxSizing", !1, i),
                        s = n ? Ke(e, u, n, a, i) : 0;
                    return (
                        a && o && (s -= Math.ceil(e["offset" + u[0].toUpperCase() + u.slice(1)] - parseFloat(i[u]) - Ke(e, u, "border", !1, i) - 0.5)),
                        s && (r = te.exec(t)) && "px" !== (r[3] || "px") && ((e.style[u] = t), (t = E.css(e, u))),
                        Je(0, t, s)
                    );
                },
            };
        }),
        (E.cssHooks.marginLeft = _e(v.reliableMarginLeft, function (e, t) {
            if (t)
                return (
                    (parseFloat(Be(e, "marginLeft")) ||
                        e.getBoundingClientRect().left -
                            Ie(e, { marginLeft: 0 }, function () {
                                return e.getBoundingClientRect().left;
                            })) + "px"
                );
        })),
        E.each({ margin: "", padding: "", border: "Width" }, function (i, o) {
            (E.cssHooks[i + o] = {
                expand: function (e) {
                    for (var t = 0, n = {}, r = "string" == typeof e ? e.split(" ") : [e]; t < 4; t++) n[i + ne[t] + o] = r[t] || r[t - 2] || r[0];
                    return n;
                },
            }),
                "margin" !== i && (E.cssHooks[i + o].set = Je);
        }),
        E.fn.extend({
            css: function (e, t) {
                return B(
                    this,
                    function (e, t, n) {
                        var r,
                            i,
                            o = {},
                            a = 0;
                        if (Array.isArray(t)) {
                            for (r = Me(e), i = t.length; a < i; a++) o[t[a]] = E.css(e, t[a], !1, r);
                            return o;
                        }
                        return void 0 !== n ? E.style(e, t, n) : E.css(e, t);
                    },
                    e,
                    t,
                    1 < arguments.length
                );
            },
        }),
        (((E.Tween = et).prototype = {
            constructor: et,
            init: function (e, t, n, r, i, o) {
                (this.elem = e), (this.prop = n), (this.easing = i || E.easing._default), (this.options = t), (this.start = this.now = this.cur()), (this.end = r), (this.unit = o || (E.cssNumber[n] ? "" : "px"));
            },
            cur: function () {
                var e = et.propHooks[this.prop];
                return e && e.get ? e.get(this) : et.propHooks._default.get(this);
            },
            run: function (e) {
                var t,
                    n = et.propHooks[this.prop];
                return (
                    this.options.duration ? (this.pos = t = E.easing[this.easing](e, this.options.duration * e, 0, 1, this.options.duration)) : (this.pos = t = e),
                    (this.now = (this.end - this.start) * t + this.start),
                    this.options.step && this.options.step.call(this.elem, this.now, this),
                    n && n.set ? n.set(this) : et.propHooks._default.set(this),
                    this
                );
            },
        }).init.prototype = et.prototype),
        ((et.propHooks = {
            _default: {
                get: function (e) {
                    var t;
                    return 1 !== e.elem.nodeType || (null != e.elem[e.prop] && null == e.elem.style[e.prop]) ? e.elem[e.prop] : (t = E.css(e.elem, e.prop, "")) && "auto" !== t ? t : 0;
                },
                set: function (e) {
                    E.fx.step[e.prop] ? E.fx.step[e.prop](e) : 1 !== e.elem.nodeType || (!E.cssHooks[e.prop] && null == e.elem.style[Ve(e.prop)]) ? (e.elem[e.prop] = e.now) : E.style(e.elem, e.prop, e.now + e.unit);
                },
            },
        }).scrollTop = et.propHooks.scrollLeft = {
            set: function (e) {
                e.elem.nodeType && e.elem.parentNode && (e.elem[e.prop] = e.now);
            },
        }),
        (E.easing = {
            linear: function (e) {
                return e;
            },
            swing: function (e) {
                return 0.5 - Math.cos(e * Math.PI) / 2;
            },
            _default: "swing",
        }),
        (E.fx = et.prototype.init),
        (E.fx.step = {});
    var tt,
        nt,
        rt,
        it,
        ot = /^(?:toggle|show|hide)$/,
        at = /queueHooks$/;
    function st() {
        nt && (!1 === S.hidden && C.requestAnimationFrame ? C.requestAnimationFrame(st) : C.setTimeout(st, E.fx.interval), E.fx.tick());
    }
    function ut() {
        return (
            C.setTimeout(function () {
                tt = void 0;
            }),
            (tt = Date.now())
        );
    }
    function lt(e, t) {
        var n,
            r = 0,
            i = { height: e };
        for (t = t ? 1 : 0; r < 4; r += 2 - t) i["margin" + (n = ne[r])] = i["padding" + n] = e;
        return t && (i.opacity = i.width = e), i;
    }
    function ct(e, t, n) {
        for (var r, i = (ft.tweeners[t] || []).concat(ft.tweeners["*"]), o = 0, a = i.length; o < a; o++) if ((r = i[o].call(n, t, e))) return r;
    }
    function ft(o, e, t) {
        var n,
            a,
            r = 0,
            i = ft.prefilters.length,
            s = E.Deferred().always(function () {
                delete u.elem;
            }),
            u = function () {
                if (a) return !1;
                for (var e = tt || ut(), t = Math.max(0, l.startTime + l.duration - e), n = 1 - (t / l.duration || 0), r = 0, i = l.tweens.length; r < i; r++) l.tweens[r].run(n);
                return s.notifyWith(o, [l, n, t]), n < 1 && i ? t : (i || s.notifyWith(o, [l, 1, 0]), s.resolveWith(o, [l]), !1);
            },
            l = s.promise({
                elem: o,
                props: E.extend({}, e),
                opts: E.extend(!0, { specialEasing: {}, easing: E.easing._default }, t),
                originalProperties: e,
                originalOptions: t,
                startTime: tt || ut(),
                duration: t.duration,
                tweens: [],
                createTween: function (e, t) {
                    var n = E.Tween(o, l.opts, e, t, l.opts.specialEasing[e] || l.opts.easing);
                    return l.tweens.push(n), n;
                },
                stop: function (e) {
                    var t = 0,
                        n = e ? l.tweens.length : 0;
                    if (a) return this;
                    for (a = !0; t < n; t++) l.tweens[t].run(1);
                    return e ? (s.notifyWith(o, [l, 1, 0]), s.resolveWith(o, [l, e])) : s.rejectWith(o, [l, e]), this;
                },
            }),
            c = l.props;
        for (
            !(function (e, t) {
                var n, r, i, o, a;
                for (n in e)
                    if (((i = t[(r = X(n))]), (o = e[n]), Array.isArray(o) && ((i = o[1]), (o = e[n] = o[0])), n !== r && ((e[r] = o), delete e[n]), (a = E.cssHooks[r]) && ("expand" in a)))
                        for (n in ((o = a.expand(o)), delete e[r], o)) (n in e) || ((e[n] = o[n]), (t[n] = i));
                    else t[r] = i;
            })(c, l.opts.specialEasing);
            r < i;
            r++
        )
            if ((n = ft.prefilters[r].call(l, o, c, l.opts))) return m(n.stop) && (E._queueHooks(l.elem, l.opts.queue).stop = n.stop.bind(n)), n;
        return (
            E.map(c, ct, l),
            m(l.opts.start) && l.opts.start.call(o, l),
            l.progress(l.opts.progress).done(l.opts.done, l.opts.complete).fail(l.opts.fail).always(l.opts.always),
            E.fx.timer(E.extend(u, { elem: o, anim: l, queue: l.opts.queue })),
            l
        );
    }
    (E.Animation = E.extend(ft, {
        tweeners: {
            "*": [
                function (e, t) {
                    var n = this.createTween(e, t);
                    return se(n.elem, e, te.exec(t), n), n;
                },
            ],
        },
        tweener: function (e, t) {
            m(e) ? ((t = e), (e = ["*"])) : (e = e.match(P));
            for (var n, r = 0, i = e.length; r < i; r++) (n = e[r]), (ft.tweeners[n] = ft.tweeners[n] || []), ft.tweeners[n].unshift(t);
        },
        prefilters: [
            function (e, t, n) {
                var r,
                    i,
                    o,
                    a,
                    s,
                    u,
                    l,
                    c,
                    f = "width" in t || "height" in t,
                    p = this,
                    d = {},
                    h = e.style,
                    g = e.nodeType && ae(e),
                    y = Y.get(e, "fxshow");
                for (r in (n.queue ||
                    (null == (a = E._queueHooks(e, "fx")).unqueued &&
                        ((a.unqueued = 0),
                        (s = a.empty.fire),
                        (a.empty.fire = function () {
                            a.unqueued || s();
                        })),
                    a.unqueued++,
                    p.always(function () {
                        p.always(function () {
                            a.unqueued--, E.queue(e, "fx").length || a.empty.fire();
                        });
                    })),
                t))
                    if (((i = t[r]), ot.test(i))) {
                        if ((delete t[r], (o = o || "toggle" === i), i === (g ? "hide" : "show"))) {
                            if ("show" !== i || !y || void 0 === y[r]) continue;
                            g = !0;
                        }
                        d[r] = (y && y[r]) || E.style(e, r);
                    }
                if ((u = !E.isEmptyObject(t)) || !E.isEmptyObject(d))
                    for (r in (f &&
                        1 === e.nodeType &&
                        ((n.overflow = [h.overflow, h.overflowX, h.overflowY]),
                        null == (l = y && y.display) && (l = Y.get(e, "display")),
                        "none" === (c = E.css(e, "display")) && (l ? (c = l) : (le([e], !0), (l = e.style.display || l), (c = E.css(e, "display")), le([e]))),
                        ("inline" === c || ("inline-block" === c && null != l)) &&
                            "none" === E.css(e, "float") &&
                            (u ||
                                (p.done(function () {
                                    h.display = l;
                                }),
                                null == l && ((c = h.display), (l = "none" === c ? "" : c))),
                            (h.display = "inline-block"))),
                    n.overflow &&
                        ((h.overflow = "hidden"),
                        p.always(function () {
                            (h.overflow = n.overflow[0]), (h.overflowX = n.overflow[1]), (h.overflowY = n.overflow[2]);
                        })),
                    (u = !1),
                    d))
                        u ||
                            (y ? "hidden" in y && (g = y.hidden) : (y = Y.access(e, "fxshow", { display: l })),
                            o && (y.hidden = !g),
                            g && le([e], !0),
                            p.done(function () {
                                for (r in (g || le([e]), Y.remove(e, "fxshow"), d)) E.style(e, r, d[r]);
                            })),
                            (u = ct(g ? y[r] : 0, r, p)),
                            r in y || ((y[r] = u.start), g && ((u.end = u.start), (u.start = 0)));
            },
        ],
        prefilter: function (e, t) {
            t ? ft.prefilters.unshift(e) : ft.prefilters.push(e);
        },
    })),
        (E.speed = function (e, t, n) {
            var r = e && "object" == typeof e ? E.extend({}, e) : { complete: n || (!n && t) || (m(e) && e), duration: e, easing: (n && t) || (t && !m(t) && t) };
            return (
                E.fx.off ? (r.duration = 0) : "number" != typeof r.duration && (r.duration in E.fx.speeds ? (r.duration = E.fx.speeds[r.duration]) : (r.duration = E.fx.speeds._default)),
                (null != r.queue && !0 !== r.queue) || (r.queue = "fx"),
                (r.old = r.complete),
                (r.complete = function () {
                    m(r.old) && r.old.call(this), r.queue && E.dequeue(this, r.queue);
                }),
                r
            );
        }),
        E.fn.extend({
            fadeTo: function (e, t, n, r) {
                return this.filter(ae).css("opacity", 0).show().end().animate({ opacity: t }, e, n, r);
            },
            animate: function (t, e, n, r) {
                var i = E.isEmptyObject(t),
                    o = E.speed(e, n, r),
                    a = function () {
                        var e = ft(this, E.extend({}, t), o);
                        (i || Y.get(this, "finish")) && e.stop(!0);
                    };
                return (a.finish = a), i || !1 === o.queue ? this.each(a) : this.queue(o.queue, a);
            },
            stop: function (i, e, o) {
                var a = function (e) {
                    var t = e.stop;
                    delete e.stop, t(o);
                };
                return (
                    "string" != typeof i && ((o = e), (e = i), (i = void 0)),
                    e && this.queue(i || "fx", []),
                    this.each(function () {
                        var e = !0,
                            t = null != i && i + "queueHooks",
                            n = E.timers,
                            r = Y.get(this);
                        if (t) r[t] && r[t].stop && a(r[t]);
                        else for (t in r) r[t] && r[t].stop && at.test(t) && a(r[t]);
                        for (t = n.length; t--; ) n[t].elem !== this || (null != i && n[t].queue !== i) || (n[t].anim.stop(o), (e = !1), n.splice(t, 1));
                        (!e && o) || E.dequeue(this, i);
                    })
                );
            },
            finish: function (a) {
                return (
                    !1 !== a && (a = a || "fx"),
                    this.each(function () {
                        var e,
                            t = Y.get(this),
                            n = t[a + "queue"],
                            r = t[a + "queueHooks"],
                            i = E.timers,
                            o = n ? n.length : 0;
                        for (t.finish = !0, E.queue(this, a, []), r && r.stop && r.stop.call(this, !0), e = i.length; e--; ) i[e].elem === this && i[e].queue === a && (i[e].anim.stop(!0), i.splice(e, 1));
                        for (e = 0; e < o; e++) n[e] && n[e].finish && n[e].finish.call(this);
                        delete t.finish;
                    })
                );
            },
        }),
        E.each(["toggle", "show", "hide"], function (e, r) {
            var i = E.fn[r];
            E.fn[r] = function (e, t, n) {
                return null == e || "boolean" == typeof e ? i.apply(this, arguments) : this.animate(lt(r, !0), e, t, n);
            };
        }),
        E.each({ slideDown: lt("show"), slideUp: lt("hide"), slideToggle: lt("toggle"), fadeIn: { opacity: "show" }, fadeOut: { opacity: "hide" }, fadeToggle: { opacity: "toggle" } }, function (e, r) {
            E.fn[e] = function (e, t, n) {
                return this.animate(r, e, t, n);
            };
        }),
        (E.timers = []),
        (E.fx.tick = function () {
            var e,
                t = 0,
                n = E.timers;
            for (tt = Date.now(); t < n.length; t++) (e = n[t])() || n[t] !== e || n.splice(t--, 1);
            n.length || E.fx.stop(), (tt = void 0);
        }),
        (E.fx.timer = function (e) {
            E.timers.push(e), E.fx.start();
        }),
        (E.fx.interval = 13),
        (E.fx.start = function () {
            nt || ((nt = !0), st());
        }),
        (E.fx.stop = function () {
            nt = null;
        }),
        (E.fx.speeds = { slow: 600, fast: 200, _default: 400 }),
        (E.fn.delay = function (r, e) {
            return (
                (r = (E.fx && E.fx.speeds[r]) || r),
                (e = e || "fx"),
                this.queue(e, function (e, t) {
                    var n = C.setTimeout(e, r);
                    t.stop = function () {
                        C.clearTimeout(n);
                    };
                })
            );
        }),
        (rt = S.createElement("input")),
        (it = S.createElement("select").appendChild(S.createElement("option"))),
        (rt.type = "checkbox"),
        (v.checkOn = "" !== rt.value),
        (v.optSelected = it.selected),
        ((rt = S.createElement("input")).value = "t"),
        (rt.type = "radio"),
        (v.radioValue = "t" === rt.value);
    var pt,
        dt = E.expr.attrHandle;
    E.fn.extend({
        attr: function (e, t) {
            return B(this, E.attr, e, t, 1 < arguments.length);
        },
        removeAttr: function (e) {
            return this.each(function () {
                E.removeAttr(this, e);
            });
        },
    }),
        E.extend({
            attr: function (e, t, n) {
                var r,
                    i,
                    o = e.nodeType;
                if (3 !== o && 8 !== o && 2 !== o)
                    return "undefined" == typeof e.getAttribute
                        ? E.prop(e, t, n)
                        : ((1 === o && E.isXMLDoc(e)) || (i = E.attrHooks[t.toLowerCase()] || (E.expr.match.bool.test(t) ? pt : void 0)),
                          void 0 !== n
                              ? null === n
                                  ? void E.removeAttr(e, t)
                                  : i && "set" in i && void 0 !== (r = i.set(e, n, t))
                                  ? r
                                  : (e.setAttribute(t, n + ""), n)
                              : i && "get" in i && null !== (r = i.get(e, t))
                              ? r
                              : null == (r = E.find.attr(e, t))
                              ? void 0
                              : r);
            },
            attrHooks: {
                type: {
                    set: function (e, t) {
                        if (!v.radioValue && "radio" === t && A(e, "input")) {
                            var n = e.value;
                            return e.setAttribute("type", t), n && (e.value = n), t;
                        }
                    },
                },
            },
            removeAttr: function (e, t) {
                var n,
                    r = 0,
                    i = t && t.match(P);
                if (i && 1 === e.nodeType) while ((n = i[r++])) e.removeAttribute(n);
            },
        }),
        (pt = {
            set: function (e, t, n) {
                return !1 === t ? E.removeAttr(e, n) : e.setAttribute(n, n), n;
            },
        }),
        E.each(E.expr.match.bool.source.match(/\w+/g), function (e, t) {
            var a = dt[t] || E.find.attr;
            dt[t] = function (e, t, n) {
                var r,
                    i,
                    o = t.toLowerCase();
                return n || ((i = dt[o]), (dt[o] = r), (r = null != a(e, t, n) ? o : null), (dt[o] = i)), r;
            };
        });
    var ht = /^(?:input|select|textarea|button)$/i,
        gt = /^(?:a|area)$/i;
    function yt(e) {
        return (e.match(P) || []).join(" ");
    }
    function vt(e) {
        return (e.getAttribute && e.getAttribute("class")) || "";
    }
    function mt(e) {
        return Array.isArray(e) ? e : ("string" == typeof e && e.match(P)) || [];
    }
    E.fn.extend({
        prop: function (e, t) {
            return B(this, E.prop, e, t, 1 < arguments.length);
        },
        removeProp: function (e) {
            return this.each(function () {
                delete this[E.propFix[e] || e];
            });
        },
    }),
        E.extend({
            prop: function (e, t, n) {
                var r,
                    i,
                    o = e.nodeType;
                if (3 !== o && 8 !== o && 2 !== o)
                    return (
                        (1 === o && E.isXMLDoc(e)) || ((t = E.propFix[t] || t), (i = E.propHooks[t])),
                        void 0 !== n ? (i && "set" in i && void 0 !== (r = i.set(e, n, t)) ? r : (e[t] = n)) : i && "get" in i && null !== (r = i.get(e, t)) ? r : e[t]
                    );
            },
            propHooks: {
                tabIndex: {
                    get: function (e) {
                        var t = E.find.attr(e, "tabindex");
                        return t ? parseInt(t, 10) : ht.test(e.nodeName) || (gt.test(e.nodeName) && e.href) ? 0 : -1;
                    },
                },
            },
            propFix: { for: "htmlFor", class: "className" },
        }),
        v.optSelected ||
            (E.propHooks.selected = {
                get: function (e) {
                    var t = e.parentNode;
                    return t && t.parentNode && t.parentNode.selectedIndex, null;
                },
                set: function (e) {
                    var t = e.parentNode;
                    t && (t.selectedIndex, t.parentNode && t.parentNode.selectedIndex);
                },
            }),
        E.each(["tabIndex", "readOnly", "maxLength", "cellSpacing", "cellPadding", "rowSpan", "colSpan", "useMap", "frameBorder", "contentEditable"], function () {
            E.propFix[this.toLowerCase()] = this;
        }),
        E.fn.extend({
            addClass: function (t) {
                var e, n, r, i, o, a;
                return m(t)
                    ? this.each(function (e) {
                          E(this).addClass(t.call(this, e, vt(this)));
                      })
                    : (e = mt(t)).length
                    ? this.each(function () {
                          if (((r = vt(this)), (n = 1 === this.nodeType && " " + yt(r) + " "))) {
                              for (o = 0; o < e.length; o++) (i = e[o]), n.indexOf(" " + i + " ") < 0 && (n += i + " ");
                              (a = yt(n)), r !== a && this.setAttribute("class", a);
                          }
                      })
                    : this;
            },
            removeClass: function (t) {
                var e, n, r, i, o, a;
                return m(t)
                    ? this.each(function (e) {
                          E(this).removeClass(t.call(this, e, vt(this)));
                      })
                    : arguments.length
                    ? (e = mt(t)).length
                        ? this.each(function () {
                              if (((r = vt(this)), (n = 1 === this.nodeType && " " + yt(r) + " "))) {
                                  for (o = 0; o < e.length; o++) {
                                      i = e[o];
                                      while (-1 < n.indexOf(" " + i + " ")) n = n.replace(" " + i + " ", " ");
                                  }
                                  (a = yt(n)), r !== a && this.setAttribute("class", a);
                              }
                          })
                        : this
                    : this.attr("class", "");
            },
            toggleClass: function (t, n) {
                var e,
                    r,
                    i,
                    o,
                    a = typeof t,
                    s = "string" === a || Array.isArray(t);
                return m(t)
                    ? this.each(function (e) {
                          E(this).toggleClass(t.call(this, e, vt(this), n), n);
                      })
                    : "boolean" == typeof n && s
                    ? n
                        ? this.addClass(t)
                        : this.removeClass(t)
                    : ((e = mt(t)),
                      this.each(function () {
                          if (s) for (o = E(this), i = 0; i < e.length; i++) (r = e[i]), o.hasClass(r) ? o.removeClass(r) : o.addClass(r);
                          else (void 0 !== t && "boolean" !== a) || ((r = vt(this)) && Y.set(this, "__className__", r), this.setAttribute && this.setAttribute("class", r || !1 === t ? "" : Y.get(this, "__className__") || ""));
                      }));
            },
            hasClass: function (e) {
                var t,
                    n,
                    r = 0;
                t = " " + e + " ";
                while ((n = this[r++])) if (1 === n.nodeType && -1 < (" " + yt(vt(n)) + " ").indexOf(t)) return !0;
                return !1;
            },
        });
    var xt = /\r/g;
    E.fn.extend({
        val: function (n) {
            var r,
                e,
                i,
                t = this[0];
            return arguments.length
                ? ((i = m(n)),
                  this.each(function (e) {
                      var t;
                      1 === this.nodeType &&
                          (null == (t = i ? n.call(this, e, E(this).val()) : n)
                              ? (t = "")
                              : "number" == typeof t
                              ? (t += "")
                              : Array.isArray(t) &&
                                (t = E.map(t, function (e) {
                                    return null == e ? "" : e + "";
                                })),
                          ((r = E.valHooks[this.type] || E.valHooks[this.nodeName.toLowerCase()]) && "set" in r && void 0 !== r.set(this, t, "value")) || (this.value = t));
                  }))
                : t
                ? (r = E.valHooks[t.type] || E.valHooks[t.nodeName.toLowerCase()]) && "get" in r && void 0 !== (e = r.get(t, "value"))
                    ? e
                    : "string" == typeof (e = t.value)
                    ? e.replace(xt, "")
                    : null == e
                    ? ""
                    : e
                : void 0;
        },
    }),
        E.extend({
            valHooks: {
                option: {
                    get: function (e) {
                        var t = E.find.attr(e, "value");
                        return null != t ? t : yt(E.text(e));
                    },
                },
                select: {
                    get: function (e) {
                        var t,
                            n,
                            r,
                            i = e.options,
                            o = e.selectedIndex,
                            a = "select-one" === e.type,
                            s = a ? null : [],
                            u = a ? o + 1 : i.length;
                        for (r = o < 0 ? u : a ? o : 0; r < u; r++)
                            if (((n = i[r]).selected || r === o) && !n.disabled && (!n.parentNode.disabled || !A(n.parentNode, "optgroup"))) {
                                if (((t = E(n).val()), a)) return t;
                                s.push(t);
                            }
                        return s;
                    },
                    set: function (e, t) {
                        var n,
                            r,
                            i = e.options,
                            o = E.makeArray(t),
                            a = i.length;
                        while (a--) ((r = i[a]).selected = -1 < E.inArray(E.valHooks.option.get(r), o)) && (n = !0);
                        return n || (e.selectedIndex = -1), o;
                    },
                },
            },
        }),
        E.each(["radio", "checkbox"], function () {
            (E.valHooks[this] = {
                set: function (e, t) {
                    if (Array.isArray(t)) return (e.checked = -1 < E.inArray(E(e).val(), t));
                },
            }),
                v.checkOn ||
                    (E.valHooks[this].get = function (e) {
                        return null === e.getAttribute("value") ? "on" : e.value;
                    });
        }),
        (v.focusin = "onfocusin" in C);
    var bt = /^(?:focusinfocus|focusoutblur)$/,
        wt = function (e) {
            e.stopPropagation();
        };
    E.extend(E.event, {
        trigger: function (e, t, n, r) {
            var i,
                o,
                a,
                s,
                u,
                l,
                c,
                f,
                p = [n || S],
                d = y.call(e, "type") ? e.type : e,
                h = y.call(e, "namespace") ? e.namespace.split(".") : [];
            if (
                ((o = f = a = n = n || S),
                3 !== n.nodeType &&
                    8 !== n.nodeType &&
                    !bt.test(d + E.event.triggered) &&
                    (-1 < d.indexOf(".") && ((d = (h = d.split(".")).shift()), h.sort()),
                    (u = d.indexOf(":") < 0 && "on" + d),
                    ((e = e[E.expando] ? e : new E.Event(d, "object" == typeof e && e)).isTrigger = r ? 2 : 3),
                    (e.namespace = h.join(".")),
                    (e.rnamespace = e.namespace ? new RegExp("(^|\\.)" + h.join("\\.(?:.*\\.|)") + "(\\.|$)") : null),
                    (e.result = void 0),
                    e.target || (e.target = n),
                    (t = null == t ? [e] : E.makeArray(t, [e])),
                    (c = E.event.special[d] || {}),
                    r || !c.trigger || !1 !== c.trigger.apply(n, t)))
            ) {
                if (!r && !c.noBubble && !x(n)) {
                    for (s = c.delegateType || d, bt.test(s + d) || (o = o.parentNode); o; o = o.parentNode) p.push(o), (a = o);
                    a === (n.ownerDocument || S) && p.push(a.defaultView || a.parentWindow || C);
                }
                i = 0;
                while ((o = p[i++]) && !e.isPropagationStopped())
                    (f = o),
                        (e.type = 1 < i ? s : c.bindType || d),
                        (l = (Y.get(o, "events") || Object.create(null))[e.type] && Y.get(o, "handle")) && l.apply(o, t),
                        (l = u && o[u]) && l.apply && V(o) && ((e.result = l.apply(o, t)), !1 === e.result && e.preventDefault());
                return (
                    (e.type = d),
                    r ||
                        e.isDefaultPrevented() ||
                        (c._default && !1 !== c._default.apply(p.pop(), t)) ||
                        !V(n) ||
                        (u &&
                            m(n[d]) &&
                            !x(n) &&
                            ((a = n[u]) && (n[u] = null),
                            (E.event.triggered = d),
                            e.isPropagationStopped() && f.addEventListener(d, wt),
                            n[d](),
                            e.isPropagationStopped() && f.removeEventListener(d, wt),
                            (E.event.triggered = void 0),
                            a && (n[u] = a))),
                    e.result
                );
            }
        },
        simulate: function (e, t, n) {
            var r = E.extend(new E.Event(), n, { type: e, isSimulated: !0 });
            E.event.trigger(r, null, t);
        },
    }),
        E.fn.extend({
            trigger: function (e, t) {
                return this.each(function () {
                    E.event.trigger(e, t, this);
                });
            },
            triggerHandler: function (e, t) {
                var n = this[0];
                if (n) return E.event.trigger(e, t, n, !0);
            },
        }),
        v.focusin ||
            E.each({ focus: "focusin", blur: "focusout" }, function (n, r) {
                var i = function (e) {
                    E.event.simulate(r, e.target, E.event.fix(e));
                };
                E.event.special[r] = {
                    setup: function () {
                        var e = this.ownerDocument || this.document || this,
                            t = Y.access(e, r);
                        t || e.addEventListener(n, i, !0), Y.access(e, r, (t || 0) + 1);
                    },
                    teardown: function () {
                        var e = this.ownerDocument || this.document || this,
                            t = Y.access(e, r) - 1;
                        t ? Y.access(e, r, t) : (e.removeEventListener(n, i, !0), Y.remove(e, r));
                    },
                };
            });
    var Tt = C.location,
        Ct = { guid: Date.now() },
        St = /\?/;
    E.parseXML = function (e) {
        var t, n;
        if (!e || "string" != typeof e) return null;
        try {
            t = new C.DOMParser().parseFromString(e, "text/xml");
        } catch (e) {}
        return (
            (n = t && t.getElementsByTagName("parsererror")[0]),
            (t && !n) ||
                E.error(
                    "Invalid XML: " +
                        (n
                            ? E.map(n.childNodes, function (e) {
                                  return e.textContent;
                              }).join("\n")
                            : e)
                ),
            t
        );
    };
    var Et = /\[\]$/,
        kt = /\r?\n/g,
        At = /^(?:submit|button|image|reset|file)$/i,
        Nt = /^(?:input|select|textarea|keygen)/i;
    function jt(n, e, r, i) {
        var t;
        if (Array.isArray(e))
            E.each(e, function (e, t) {
                r || Et.test(n) ? i(n, t) : jt(n + "[" + ("object" == typeof t && null != t ? e : "") + "]", t, r, i);
            });
        else if (r || "object" !== w(e)) i(n, e);
        else for (t in e) jt(n + "[" + t + "]", e[t], r, i);
    }
    (E.param = function (e, t) {
        var n,
            r = [],
            i = function (e, t) {
                var n = m(t) ? t() : t;
                r[r.length] = encodeURIComponent(e) + "=" + encodeURIComponent(null == n ? "" : n);
            };
        if (null == e) return "";
        if (Array.isArray(e) || (e.jquery && !E.isPlainObject(e)))
            E.each(e, function () {
                i(this.name, this.value);
            });
        else for (n in e) jt(n, e[n], t, i);
        return r.join("&");
    }),
        E.fn.extend({
            serialize: function () {
                return E.param(this.serializeArray());
            },
            serializeArray: function () {
                return this.map(function () {
                    var e = E.prop(this, "elements");
                    return e ? E.makeArray(e) : this;
                })
                    .filter(function () {
                        var e = this.type;
                        return this.name && !E(this).is(":disabled") && Nt.test(this.nodeName) && !At.test(e) && (this.checked || !pe.test(e));
                    })
                    .map(function (e, t) {
                        var n = E(this).val();
                        return null == n
                            ? null
                            : Array.isArray(n)
                            ? E.map(n, function (e) {
                                  return { name: t.name, value: e.replace(kt, "\r\n") };
                              })
                            : { name: t.name, value: n.replace(kt, "\r\n") };
                    })
                    .get();
            },
        });
    var Dt = /%20/g,
        qt = /#.*$/,
        Lt = /([?&])_=[^&]*/,
        Ht = /^(.*?):[ \t]*([^\r\n]*)$/gm,
        Ot = /^(?:GET|HEAD)$/,
        Pt = /^\/\//,
        Rt = {},
        Mt = {},
        It = "*/".concat("*"),
        Wt = S.createElement("a");
    function Ft(o) {
        return function (e, t) {
            "string" != typeof e && ((t = e), (e = "*"));
            var n,
                r = 0,
                i = e.toLowerCase().match(P) || [];
            if (m(t)) while ((n = i[r++])) "+" === n[0] ? ((n = n.slice(1) || "*"), (o[n] = o[n] || []).unshift(t)) : (o[n] = o[n] || []).push(t);
        };
    }
    function $t(t, i, o, a) {
        var s = {},
            u = t === Mt;
        function l(e) {
            var r;
            return (
                (s[e] = !0),
                E.each(t[e] || [], function (e, t) {
                    var n = t(i, o, a);
                    return "string" != typeof n || u || s[n] ? (u ? !(r = n) : void 0) : (i.dataTypes.unshift(n), l(n), !1);
                }),
                r
            );
        }
        return l(i.dataTypes[0]) || (!s["*"] && l("*"));
    }
    function Bt(e, t) {
        var n,
            r,
            i = E.ajaxSettings.flatOptions || {};
        for (n in t) void 0 !== t[n] && ((i[n] ? e : r || (r = {}))[n] = t[n]);
        return r && E.extend(!0, e, r), e;
    }
    (Wt.href = Tt.href),
        E.extend({
            active: 0,
            lastModified: {},
            etag: {},
            ajaxSettings: {
                url: Tt.href,
                type: "GET",
                isLocal: /^(?:about|app|app-storage|.+-extension|file|res|widget):$/.test(Tt.protocol),
                global: !0,
                processData: !0,
                async: !0,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                accepts: { "*": It, text: "text/plain", html: "text/html", xml: "application/xml, text/xml", json: "application/json, text/javascript" },
                contents: { xml: /\bxml\b/, html: /\bhtml/, json: /\bjson\b/ },
                responseFields: { xml: "responseXML", text: "responseText", json: "responseJSON" },
                converters: { "* text": String, "text html": !0, "text json": JSON.parse, "text xml": E.parseXML },
                flatOptions: { url: !0, context: !0 },
            },
            ajaxSetup: function (e, t) {
                return t ? Bt(Bt(e, E.ajaxSettings), t) : Bt(E.ajaxSettings, e);
            },
            ajaxPrefilter: Ft(Rt),
            ajaxTransport: Ft(Mt),
            ajax: function (e, t) {
                "object" == typeof e && ((t = e), (e = void 0)), (t = t || {});
                var c,
                    f,
                    p,
                    n,
                    d,
                    r,
                    h,
                    g,
                    i,
                    o,
                    y = E.ajaxSetup({}, t),
                    v = y.context || y,
                    m = y.context && (v.nodeType || v.jquery) ? E(v) : E.event,
                    x = E.Deferred(),
                    b = E.Callbacks("once memory"),
                    w = y.statusCode || {},
                    a = {},
                    s = {},
                    u = "canceled",
                    T = {
                        readyState: 0,
                        getResponseHeader: function (e) {
                            var t;
                            if (h) {
                                if (!n) {
                                    n = {};
                                    while ((t = Ht.exec(p))) n[t[1].toLowerCase() + " "] = (n[t[1].toLowerCase() + " "] || []).concat(t[2]);
                                }
                                t = n[e.toLowerCase() + " "];
                            }
                            return null == t ? null : t.join(", ");
                        },
                        getAllResponseHeaders: function () {
                            return h ? p : null;
                        },
                        setRequestHeader: function (e, t) {
                            return null == h && ((e = s[e.toLowerCase()] = s[e.toLowerCase()] || e), (a[e] = t)), this;
                        },
                        overrideMimeType: function (e) {
                            return null == h && (y.mimeType = e), this;
                        },
                        statusCode: function (e) {
                            var t;
                            if (e)
                                if (h) T.always(e[T.status]);
                                else for (t in e) w[t] = [w[t], e[t]];
                            return this;
                        },
                        abort: function (e) {
                            var t = e || u;
                            return c && c.abort(t), l(0, t), this;
                        },
                    };
                if (
                    (x.promise(T),
                    (y.url = ((e || y.url || Tt.href) + "").replace(Pt, Tt.protocol + "//")),
                    (y.type = t.method || t.type || y.method || y.type),
                    (y.dataTypes = (y.dataType || "*").toLowerCase().match(P) || [""]),
                    null == y.crossDomain)
                ) {
                    r = S.createElement("a");
                    try {
                        (r.href = y.url), (r.href = r.href), (y.crossDomain = Wt.protocol + "//" + Wt.host != r.protocol + "//" + r.host);
                    } catch (e) {
                        y.crossDomain = !0;
                    }
                }
                if ((y.data && y.processData && "string" != typeof y.data && (y.data = E.param(y.data, y.traditional)), $t(Rt, y, t, T), h)) return T;
                for (i in ((g = E.event && y.global) && 0 == E.active++ && E.event.trigger("ajaxStart"),
                (y.type = y.type.toUpperCase()),
                (y.hasContent = !Ot.test(y.type)),
                (f = y.url.replace(qt, "")),
                y.hasContent
                    ? y.data && y.processData && 0 === (y.contentType || "").indexOf("application/x-www-form-urlencoded") && (y.data = y.data.replace(Dt, "+"))
                    : ((o = y.url.slice(f.length)),
                      y.data && (y.processData || "string" == typeof y.data) && ((f += (St.test(f) ? "&" : "?") + y.data), delete y.data),
                      !1 === y.cache && ((f = f.replace(Lt, "$1")), (o = (St.test(f) ? "&" : "?") + "_=" + Ct.guid++ + o)),
                      (y.url = f + o)),
                y.ifModified && (E.lastModified[f] && T.setRequestHeader("If-Modified-Since", E.lastModified[f]), E.etag[f] && T.setRequestHeader("If-None-Match", E.etag[f])),
                ((y.data && y.hasContent && !1 !== y.contentType) || t.contentType) && T.setRequestHeader("Content-Type", y.contentType),
                T.setRequestHeader("Accept", y.dataTypes[0] && y.accepts[y.dataTypes[0]] ? y.accepts[y.dataTypes[0]] + ("*" !== y.dataTypes[0] ? ", " + It + "; q=0.01" : "") : y.accepts["*"]),
                y.headers))
                    T.setRequestHeader(i, y.headers[i]);
                if (y.beforeSend && (!1 === y.beforeSend.call(v, T, y) || h)) return T.abort();
                if (((u = "abort"), b.add(y.complete), T.done(y.success), T.fail(y.error), (c = $t(Mt, y, t, T)))) {
                    if (((T.readyState = 1), g && m.trigger("ajaxSend", [T, y]), h)) return T;
                    y.async &&
                        0 < y.timeout &&
                        (d = C.setTimeout(function () {
                            T.abort("timeout");
                        }, y.timeout));
                    try {
                        (h = !1), c.send(a, l);
                    } catch (e) {
                        if (h) throw e;
                        l(-1, e);
                    }
                } else l(-1, "No Transport");
                function l(e, t, n, r) {
                    var i,
                        o,
                        a,
                        s,
                        u,
                        l = t;
                    h ||
                        ((h = !0),
                        d && C.clearTimeout(d),
                        (c = void 0),
                        (p = r || ""),
                        (T.readyState = 0 < e ? 4 : 0),
                        (i = (200 <= e && e < 300) || 304 === e),
                        n &&
                            (s = (function (e, t, n) {
                                var r,
                                    i,
                                    o,
                                    a,
                                    s = e.contents,
                                    u = e.dataTypes;
                                while ("*" === u[0]) u.shift(), void 0 === r && (r = e.mimeType || t.getResponseHeader("Content-Type"));
                                if (r)
                                    for (i in s)
                                        if (s[i] && s[i].test(r)) {
                                            u.unshift(i);
                                            break;
                                        }
                                if (u[0] in n) o = u[0];
                                else {
                                    for (i in n) {
                                        if (!u[0] || e.converters[i + " " + u[0]]) {
                                            o = i;
                                            break;
                                        }
                                        a || (a = i);
                                    }
                                    o = o || a;
                                }
                                if (o) return o !== u[0] && u.unshift(o), n[o];
                            })(y, T, n)),
                        !i && -1 < E.inArray("script", y.dataTypes) && E.inArray("json", y.dataTypes) < 0 && (y.converters["text script"] = function () {}),
                        (s = (function (e, t, n, r) {
                            var i,
                                o,
                                a,
                                s,
                                u,
                                l = {},
                                c = e.dataTypes.slice();
                            if (c[1]) for (a in e.converters) l[a.toLowerCase()] = e.converters[a];
                            o = c.shift();
                            while (o)
                                if ((e.responseFields[o] && (n[e.responseFields[o]] = t), !u && r && e.dataFilter && (t = e.dataFilter(t, e.dataType)), (u = o), (o = c.shift())))
                                    if ("*" === o) o = u;
                                    else if ("*" !== u && u !== o) {
                                        if (!(a = l[u + " " + o] || l["* " + o]))
                                            for (i in l)
                                                if ((s = i.split(" "))[1] === o && (a = l[u + " " + s[0]] || l["* " + s[0]])) {
                                                    !0 === a ? (a = l[i]) : !0 !== l[i] && ((o = s[0]), c.unshift(s[1]));
                                                    break;
                                                }
                                        if (!0 !== a)
                                            if (a && e["throws"]) t = a(t);
                                            else
                                                try {
                                                    t = a(t);
                                                } catch (e) {
                                                    return { state: "parsererror", error: a ? e : "No conversion from " + u + " to " + o };
                                                }
                                    }
                            return { state: "success", data: t };
                        })(y, s, T, i)),
                        i
                            ? (y.ifModified && ((u = T.getResponseHeader("Last-Modified")) && (E.lastModified[f] = u), (u = T.getResponseHeader("etag")) && (E.etag[f] = u)),
                              204 === e || "HEAD" === y.type ? (l = "nocontent") : 304 === e ? (l = "notmodified") : ((l = s.state), (o = s.data), (i = !(a = s.error))))
                            : ((a = l), (!e && l) || ((l = "error"), e < 0 && (e = 0))),
                        (T.status = e),
                        (T.statusText = (t || l) + ""),
                        i ? x.resolveWith(v, [o, l, T]) : x.rejectWith(v, [T, l, a]),
                        T.statusCode(w),
                        (w = void 0),
                        g && m.trigger(i ? "ajaxSuccess" : "ajaxError", [T, y, i ? o : a]),
                        b.fireWith(v, [T, l]),
                        g && (m.trigger("ajaxComplete", [T, y]), --E.active || E.event.trigger("ajaxStop")));
                }
                return T;
            },
            getJSON: function (e, t, n) {
                return E.get(e, t, n, "json");
            },
            getScript: function (e, t) {
                return E.get(e, void 0, t, "script");
            },
        }),
        E.each(["get", "post"], function (e, i) {
            E[i] = function (e, t, n, r) {
                return m(t) && ((r = r || n), (n = t), (t = void 0)), E.ajax(E.extend({ url: e, type: i, dataType: r, data: t, success: n }, E.isPlainObject(e) && e));
            };
        }),
        E.ajaxPrefilter(function (e) {
            var t;
            for (t in e.headers) "content-type" === t.toLowerCase() && (e.contentType = e.headers[t] || "");
        }),
        (E._evalUrl = function (e, t, n) {
            return E.ajax({
                url: e,
                type: "GET",
                dataType: "script",
                cache: !0,
                async: !1,
                global: !1,
                converters: { "text script": function () {} },
                dataFilter: function (e) {
                    E.globalEval(e, t, n);
                },
            });
        }),
        E.fn.extend({
            wrapAll: function (e) {
                var t;
                return (
                    this[0] &&
                        (m(e) && (e = e.call(this[0])),
                        (t = E(e, this[0].ownerDocument).eq(0).clone(!0)),
                        this[0].parentNode && t.insertBefore(this[0]),
                        t
                            .map(function () {
                                var e = this;
                                while (e.firstElementChild) e = e.firstElementChild;
                                return e;
                            })
                            .append(this)),
                    this
                );
            },
            wrapInner: function (n) {
                return m(n)
                    ? this.each(function (e) {
                          E(this).wrapInner(n.call(this, e));
                      })
                    : this.each(function () {
                          var e = E(this),
                              t = e.contents();
                          t.length ? t.wrapAll(n) : e.append(n);
                      });
            },
            wrap: function (t) {
                var n = m(t);
                return this.each(function (e) {
                    E(this).wrapAll(n ? t.call(this, e) : t);
                });
            },
            unwrap: function (e) {
                return (
                    this.parent(e)
                        .not("body")
                        .each(function () {
                            E(this).replaceWith(this.childNodes);
                        }),
                    this
                );
            },
        }),
        (E.expr.pseudos.hidden = function (e) {
            return !E.expr.pseudos.visible(e);
        }),
        (E.expr.pseudos.visible = function (e) {
            return !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
        }),
        (E.ajaxSettings.xhr = function () {
            try {
                return new C.XMLHttpRequest();
            } catch (e) {}
        });
    var _t = { 0: 200, 1223: 204 },
        zt = E.ajaxSettings.xhr();
    (v.cors = !!zt && "withCredentials" in zt),
        (v.ajax = zt = !!zt),
        E.ajaxTransport(function (i) {
            var o, a;
            if (v.cors || (zt && !i.crossDomain))
                return {
                    send: function (e, t) {
                        var n,
                            r = i.xhr();
                        if ((r.open(i.type, i.url, i.async, i.username, i.password), i.xhrFields)) for (n in i.xhrFields) r[n] = i.xhrFields[n];
                        for (n in (i.mimeType && r.overrideMimeType && r.overrideMimeType(i.mimeType), i.crossDomain || e["X-Requested-With"] || (e["X-Requested-With"] = "XMLHttpRequest"), e)) r.setRequestHeader(n, e[n]);
                        (o = function (e) {
                            return function () {
                                o &&
                                    ((o = a = r.onload = r.onerror = r.onabort = r.ontimeout = r.onreadystatechange = null),
                                    "abort" === e
                                        ? r.abort()
                                        : "error" === e
                                        ? "number" != typeof r.status
                                            ? t(0, "error")
                                            : t(r.status, r.statusText)
                                        : t(_t[r.status] || r.status, r.statusText, "text" !== (r.responseType || "text") || "string" != typeof r.responseText ? { binary: r.response } : { text: r.responseText }, r.getAllResponseHeaders()));
                            };
                        }),
                            (r.onload = o()),
                            (a = r.onerror = r.ontimeout = o("error")),
                            void 0 !== r.onabort
                                ? (r.onabort = a)
                                : (r.onreadystatechange = function () {
                                      4 === r.readyState &&
                                          C.setTimeout(function () {
                                              o && a();
                                          });
                                  }),
                            (o = o("abort"));
                        try {
                            r.send((i.hasContent && i.data) || null);
                        } catch (e) {
                            if (o) throw e;
                        }
                    },
                    abort: function () {
                        o && o();
                    },
                };
        }),
        E.ajaxPrefilter(function (e) {
            e.crossDomain && (e.contents.script = !1);
        }),
        E.ajaxSetup({
            accepts: { script: "text/javascript, application/javascript, application/ecmascript, application/x-ecmascript" },
            contents: { script: /\b(?:java|ecma)script\b/ },
            converters: {
                "text script": function (e) {
                    return E.globalEval(e), e;
                },
            },
        }),
        E.ajaxPrefilter("script", function (e) {
            void 0 === e.cache && (e.cache = !1), e.crossDomain && (e.type = "GET");
        }),
        E.ajaxTransport("script", function (n) {
            var r, i;
            if (n.crossDomain || n.scriptAttrs)
                return {
                    send: function (e, t) {
                        (r = E("<script>")
                            .attr(n.scriptAttrs || {})
                            .prop({ charset: n.scriptCharset, src: n.url })
                            .on(
                                "load error",
                                (i = function (e) {
                                    r.remove(), (i = null), e && t("error" === e.type ? 404 : 200, e.type);
                                })
                            )),
                            S.head.appendChild(r[0]);
                    },
                    abort: function () {
                        i && i();
                    },
                };
        });
    var Ut,
        Xt = [],
        Vt = /(=)\?(?=&|$)|\?\?/;
    E.ajaxSetup({
        jsonp: "callback",
        jsonpCallback: function () {
            var e = Xt.pop() || E.expando + "_" + Ct.guid++;
            return (this[e] = !0), e;
        },
    }),
        E.ajaxPrefilter("json jsonp", function (e, t, n) {
            var r,
                i,
                o,
                a = !1 !== e.jsonp && (Vt.test(e.url) ? "url" : "string" == typeof e.data && 0 === (e.contentType || "").indexOf("application/x-www-form-urlencoded") && Vt.test(e.data) && "data");
            if (a || "jsonp" === e.dataTypes[0])
                return (
                    (r = e.jsonpCallback = m(e.jsonpCallback) ? e.jsonpCallback() : e.jsonpCallback),
                    a ? (e[a] = e[a].replace(Vt, "$1" + r)) : !1 !== e.jsonp && (e.url += (St.test(e.url) ? "&" : "?") + e.jsonp + "=" + r),
                    (e.converters["script json"] = function () {
                        return o || E.error(r + " was not called"), o[0];
                    }),
                    (e.dataTypes[0] = "json"),
                    (i = C[r]),
                    (C[r] = function () {
                        o = arguments;
                    }),
                    n.always(function () {
                        void 0 === i ? E(C).removeProp(r) : (C[r] = i), e[r] && ((e.jsonpCallback = t.jsonpCallback), Xt.push(r)), o && m(i) && i(o[0]), (o = i = void 0);
                    }),
                    "script"
                );
        }),
        (v.createHTMLDocument = (((Ut = S.implementation.createHTMLDocument("").body).innerHTML = "<form></form><form></form>"), 2 === Ut.childNodes.length)),
        (E.parseHTML = function (e, t, n) {
            return "string" != typeof e
                ? []
                : ("boolean" == typeof t && ((n = t), (t = !1)),
                  t || (v.createHTMLDocument ? (((r = (t = S.implementation.createHTMLDocument("")).createElement("base")).href = S.location.href), t.head.appendChild(r)) : (t = S)),
                  (o = !n && []),
                  (i = N.exec(e)) ? [t.createElement(i[1])] : ((i = xe([e], t, o)), o && o.length && E(o).remove(), E.merge([], i.childNodes)));
            var r, i, o;
        }),
        (E.fn.load = function (e, t, n) {
            var r,
                i,
                o,
                a = this,
                s = e.indexOf(" ");
            return (
                -1 < s && ((r = yt(e.slice(s))), (e = e.slice(0, s))),
                m(t) ? ((n = t), (t = void 0)) : t && "object" == typeof t && (i = "POST"),
                0 < a.length &&
                    E.ajax({ url: e, type: i || "GET", dataType: "html", data: t })
                        .done(function (e) {
                            (o = arguments), a.html(r ? E("<div>").append(E.parseHTML(e)).find(r) : e);
                        })
                        .always(
                            n &&
                                function (e, t) {
                                    a.each(function () {
                                        n.apply(this, o || [e.responseText, t, e]);
                                    });
                                }
                        ),
                this
            );
        }),
        (E.expr.pseudos.animated = function (t) {
            return E.grep(E.timers, function (e) {
                return t === e.elem;
            }).length;
        }),
        (E.offset = {
            setOffset: function (e, t, n) {
                var r,
                    i,
                    o,
                    a,
                    s,
                    u,
                    l = E.css(e, "position"),
                    c = E(e),
                    f = {};
                "static" === l && (e.style.position = "relative"),
                    (s = c.offset()),
                    (o = E.css(e, "top")),
                    (u = E.css(e, "left")),
                    ("absolute" === l || "fixed" === l) && -1 < (o + u).indexOf("auto") ? ((a = (r = c.position()).top), (i = r.left)) : ((a = parseFloat(o) || 0), (i = parseFloat(u) || 0)),
                    m(t) && (t = t.call(e, n, E.extend({}, s))),
                    null != t.top && (f.top = t.top - s.top + a),
                    null != t.left && (f.left = t.left - s.left + i),
                    "using" in t ? t.using.call(e, f) : c.css(f);
            },
        }),
        E.fn.extend({
            offset: function (t) {
                if (arguments.length)
                    return void 0 === t
                        ? this
                        : this.each(function (e) {
                              E.offset.setOffset(this, t, e);
                          });
                var e,
                    n,
                    r = this[0];
                return r ? (r.getClientRects().length ? ((e = r.getBoundingClientRect()), (n = r.ownerDocument.defaultView), { top: e.top + n.pageYOffset, left: e.left + n.pageXOffset }) : { top: 0, left: 0 }) : void 0;
            },
            position: function () {
                if (this[0]) {
                    var e,
                        t,
                        n,
                        r = this[0],
                        i = { top: 0, left: 0 };
                    if ("fixed" === E.css(r, "position")) t = r.getBoundingClientRect();
                    else {
                        (t = this.offset()), (n = r.ownerDocument), (e = r.offsetParent || n.documentElement);
                        while (e && (e === n.body || e === n.documentElement) && "static" === E.css(e, "position")) e = e.parentNode;
                        e && e !== r && 1 === e.nodeType && (((i = E(e).offset()).top += E.css(e, "borderTopWidth", !0)), (i.left += E.css(e, "borderLeftWidth", !0)));
                    }
                    return { top: t.top - i.top - E.css(r, "marginTop", !0), left: t.left - i.left - E.css(r, "marginLeft", !0) };
                }
            },
            offsetParent: function () {
                return this.map(function () {
                    var e = this.offsetParent;
                    while (e && "static" === E.css(e, "position")) e = e.offsetParent;
                    return e || re;
                });
            },
        }),
        E.each({ scrollLeft: "pageXOffset", scrollTop: "pageYOffset" }, function (t, i) {
            var o = "pageYOffset" === i;
            E.fn[t] = function (e) {
                return B(
                    this,
                    function (e, t, n) {
                        var r;
                        if ((x(e) ? (r = e) : 9 === e.nodeType && (r = e.defaultView), void 0 === n)) return r ? r[i] : e[t];
                        r ? r.scrollTo(o ? r.pageXOffset : n, o ? n : r.pageYOffset) : (e[t] = n);
                    },
                    t,
                    e,
                    arguments.length
                );
            };
        }),
        E.each(["top", "left"], function (e, n) {
            E.cssHooks[n] = _e(v.pixelPosition, function (e, t) {
                if (t) return (t = Be(e, n)), Pe.test(t) ? E(e).position()[n] + "px" : t;
            });
        }),
        E.each({ Height: "height", Width: "width" }, function (a, s) {
            E.each({ padding: "inner" + a, content: s, "": "outer" + a }, function (r, o) {
                E.fn[o] = function (e, t) {
                    var n = arguments.length && (r || "boolean" != typeof e),
                        i = r || (!0 === e || !0 === t ? "margin" : "border");
                    return B(
                        this,
                        function (e, t, n) {
                            var r;
                            return x(e)
                                ? 0 === o.indexOf("outer")
                                    ? e["inner" + a]
                                    : e.document.documentElement["client" + a]
                                : 9 === e.nodeType
                                ? ((r = e.documentElement), Math.max(e.body["scroll" + a], r["scroll" + a], e.body["offset" + a], r["offset" + a], r["client" + a]))
                                : void 0 === n
                                ? E.css(e, t, i)
                                : E.style(e, t, n, i);
                        },
                        s,
                        n ? e : void 0,
                        n
                    );
                };
            });
        }),
        E.each(["ajaxStart", "ajaxStop", "ajaxComplete", "ajaxError", "ajaxSuccess", "ajaxSend"], function (e, t) {
            E.fn[t] = function (e) {
                return this.on(t, e);
            };
        }),
        E.fn.extend({
            bind: function (e, t, n) {
                return this.on(e, null, t, n);
            },
            unbind: function (e, t) {
                return this.off(e, null, t);
            },
            delegate: function (e, t, n, r) {
                return this.on(t, e, n, r);
            },
            undelegate: function (e, t, n) {
                return 1 === arguments.length ? this.off(e, "**") : this.off(t, e || "**", n);
            },
            hover: function (e, t) {
                return this.mouseenter(e).mouseleave(t || e);
            },
        }),
        E.each("blur focus focusin focusout resize scroll click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup contextmenu".split(" "), function (e, n) {
            E.fn[n] = function (e, t) {
                return 0 < arguments.length ? this.on(n, null, e, t) : this.trigger(n);
            };
        });
    var Gt = /^[\s\uFEFF\xA0]+|([^\s\uFEFF\xA0])[\s\uFEFF\xA0]+$/g;
    (E.proxy = function (e, t) {
        var n, r, i;
        if (("string" == typeof t && ((n = e[t]), (t = e), (e = n)), m(e)))
            return (
                (r = s.call(arguments, 2)),
                ((i = function () {
                    return e.apply(t || this, r.concat(s.call(arguments)));
                }).guid = e.guid = e.guid || E.guid++),
                i
            );
    }),
        (E.holdReady = function (e) {
            e ? E.readyWait++ : E.ready(!0);
        }),
        (E.isArray = Array.isArray),
        (E.parseJSON = JSON.parse),
        (E.nodeName = A),
        (E.isFunction = m),
        (E.isWindow = x),
        (E.camelCase = X),
        (E.type = w),
        (E.now = Date.now),
        (E.isNumeric = function (e) {
            var t = E.type(e);
            return ("number" === t || "string" === t) && !isNaN(e - parseFloat(e));
        }),
        (E.trim = function (e) {
            return null == e ? "" : (e + "").replace(Gt, "$1");
        }),
        "function" == typeof define &&
            define.amd &&
            define("jquery", [], function () {
                return E;
            });
    var Yt = C.jQuery,
        Qt = C.$;
    return (
        (E.noConflict = function (e) {
            return C.$ === E && (C.$ = Qt), e && C.jQuery === E && (C.jQuery = Yt), E;
        }),
        "undefined" == typeof e && (C.jQuery = C.$ = E),
        E
    );
});
/*!
 * Bootstrap v5.2.3 (https://getbootstrap.com/)
 * Copyright 2011-2022 The Bootstrap Authors (https://github.com/twbs/bootstrap/graphs/contributors)
 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/main/LICENSE)
 */
!(function (t, e) {
    "object" == typeof exports && "undefined" != typeof module
        ? (module.exports = e(require("@popperjs/core")))
        : "function" == typeof define && define.amd
        ? define(["@popperjs/core"], e)
        : ((t = "undefined" != typeof globalThis ? globalThis : t || self).bootstrap = e(t.Popper));
})(this, function (t) {
    "use strict";
    function e(t) {
        if (t && t.__esModule) return t;
        const e = Object.create(null, { [Symbol.toStringTag]: { value: "Module" } });
        if (t)
            for (const i in t)
                if ("default" !== i) {
                    const s = Object.getOwnPropertyDescriptor(t, i);
                    Object.defineProperty(e, i, s.get ? s : { enumerable: !0, get: () => t[i] });
                }
        return (e.default = t), Object.freeze(e);
    }
    const i = e(t),
        s = "transitionend",
        n = (t) => {
            let e = t.getAttribute("data-bs-target");
            if (!e || "#" === e) {
                let i = t.getAttribute("href");
                if (!i || (!i.includes("#") && !i.startsWith("."))) return null;
                i.includes("#") && !i.startsWith("#") && (i = `#${i.split("#")[1]}`), (e = i && "#" !== i ? i.trim() : null);
            }
            return e;
        },
        o = (t) => {
            const e = n(t);
            return e && document.querySelector(e) ? e : null;
        },
        r = (t) => {
            const e = n(t);
            return e ? document.querySelector(e) : null;
        },
        a = (t) => {
            t.dispatchEvent(new Event(s));
        },
        l = (t) => !(!t || "object" != typeof t) && (void 0 !== t.jquery && (t = t[0]), void 0 !== t.nodeType),
        c = (t) => (l(t) ? (t.jquery ? t[0] : t) : "string" == typeof t && t.length > 0 ? document.querySelector(t) : null),
        h = (t) => {
            if (!l(t) || 0 === t.getClientRects().length) return !1;
            const e = "visible" === getComputedStyle(t).getPropertyValue("visibility"),
                i = t.closest("details:not([open])");
            if (!i) return e;
            if (i !== t) {
                const e = t.closest("summary");
                if (e && e.parentNode !== i) return !1;
                if (null === e) return !1;
            }
            return e;
        },
        d = (t) => !t || t.nodeType !== Node.ELEMENT_NODE || !!t.classList.contains("disabled") || (void 0 !== t.disabled ? t.disabled : t.hasAttribute("disabled") && "false" !== t.getAttribute("disabled")),
        u = (t) => {
            if (!document.documentElement.attachShadow) return null;
            if ("function" == typeof t.getRootNode) {
                const e = t.getRootNode();
                return e instanceof ShadowRoot ? e : null;
            }
            return t instanceof ShadowRoot ? t : t.parentNode ? u(t.parentNode) : null;
        },
        _ = () => {},
        g = (t) => {
            t.offsetHeight;
        },
        f = () => (window.jQuery && !document.body.hasAttribute("data-bs-no-jquery") ? window.jQuery : null),
        p = [],
        m = () => "rtl" === document.documentElement.dir,
        b = (t) => {
            var e;
            (e = () => {
                const e = f();
                if (e) {
                    const i = t.NAME,
                        s = e.fn[i];
                    (e.fn[i] = t.jQueryInterface), (e.fn[i].Constructor = t), (e.fn[i].noConflict = () => ((e.fn[i] = s), t.jQueryInterface));
                }
            }),
                "loading" === document.readyState
                    ? (p.length ||
                          document.addEventListener("DOMContentLoaded", () => {
                              for (const t of p) t();
                          }),
                      p.push(e))
                    : e();
        },
        v = (t) => {
            "function" == typeof t && t();
        },
        y = (t, e, i = !0) => {
            if (!i) return void v(t);
            const n =
                ((t) => {
                    if (!t) return 0;
                    let { transitionDuration: e, transitionDelay: i } = window.getComputedStyle(t);
                    const s = Number.parseFloat(e),
                        n = Number.parseFloat(i);
                    return s || n ? ((e = e.split(",")[0]), (i = i.split(",")[0]), 1e3 * (Number.parseFloat(e) + Number.parseFloat(i))) : 0;
                })(e) + 5;
            let o = !1;
            const r = ({ target: i }) => {
                i === e && ((o = !0), e.removeEventListener(s, r), v(t));
            };
            e.addEventListener(s, r),
                setTimeout(() => {
                    o || a(e);
                }, n);
        },
        w = (t, e, i, s) => {
            const n = t.length;
            let o = t.indexOf(e);
            return -1 === o ? (!i && s ? t[n - 1] : t[0]) : ((o += i ? 1 : -1), s && (o = (o + n) % n), t[Math.max(0, Math.min(o, n - 1))]);
        },
        A = /[^.]*(?=\..*)\.|.*/,
        E = /\..*/,
        C = /::\d+$/,
        T = {};
    let k = 1;
    const L = { mouseenter: "mouseover", mouseleave: "mouseout" },
        O = new Set([
            "click",
            "dblclick",
            "mouseup",
            "mousedown",
            "contextmenu",
            "mousewheel",
            "DOMMouseScroll",
            "mouseover",
            "mouseout",
            "mousemove",
            "selectstart",
            "selectend",
            "keydown",
            "keypress",
            "keyup",
            "orientationchange",
            "touchstart",
            "touchmove",
            "touchend",
            "touchcancel",
            "pointerdown",
            "pointermove",
            "pointerup",
            "pointerleave",
            "pointercancel",
            "gesturestart",
            "gesturechange",
            "gestureend",
            "focus",
            "blur",
            "change",
            "reset",
            "select",
            "submit",
            "focusin",
            "focusout",
            "load",
            "unload",
            "beforeunload",
            "resize",
            "move",
            "DOMContentLoaded",
            "readystatechange",
            "error",
            "abort",
            "scroll",
        ]);
    function I(t, e) {
        return (e && `${e}::${k++}`) || t.uidEvent || k++;
    }
    function S(t) {
        const e = I(t);
        return (t.uidEvent = e), (T[e] = T[e] || {}), T[e];
    }
    function D(t, e, i = null) {
        return Object.values(t).find((t) => t.callable === e && t.delegationSelector === i);
    }
    function N(t, e, i) {
        const s = "string" == typeof e,
            n = s ? i : e || i;
        let o = j(t);
        return O.has(o) || (o = t), [s, n, o];
    }
    function P(t, e, i, s, n) {
        if ("string" != typeof e || !t) return;
        let [o, r, a] = N(e, i, s);
        if (e in L) {
            const t = (t) =>
                function (e) {
                    if (!e.relatedTarget || (e.relatedTarget !== e.delegateTarget && !e.delegateTarget.contains(e.relatedTarget))) return t.call(this, e);
                };
            r = t(r);
        }
        const l = S(t),
            c = l[a] || (l[a] = {}),
            h = D(c, r, o ? i : null);
        if (h) return void (h.oneOff = h.oneOff && n);
        const d = I(r, e.replace(A, "")),
            u = o
                ? (function (t, e, i) {
                      return function s(n) {
                          const o = t.querySelectorAll(e);
                          for (let { target: r } = n; r && r !== this; r = r.parentNode) for (const a of o) if (a === r) return F(n, { delegateTarget: r }), s.oneOff && $.off(t, n.type, e, i), i.apply(r, [n]);
                      };
                  })(t, i, r)
                : (function (t, e) {
                      return function i(s) {
                          return F(s, { delegateTarget: t }), i.oneOff && $.off(t, s.type, e), e.apply(t, [s]);
                      };
                  })(t, r);
        (u.delegationSelector = o ? i : null), (u.callable = r), (u.oneOff = n), (u.uidEvent = d), (c[d] = u), t.addEventListener(a, u, o);
    }
    function x(t, e, i, s, n) {
        const o = D(e[i], s, n);
        o && (t.removeEventListener(i, o, Boolean(n)), delete e[i][o.uidEvent]);
    }
    function M(t, e, i, s) {
        const n = e[i] || {};
        for (const o of Object.keys(n))
            if (o.includes(s)) {
                const s = n[o];
                x(t, e, i, s.callable, s.delegationSelector);
            }
    }
    function j(t) {
        return (t = t.replace(E, "")), L[t] || t;
    }
    const $ = {
        on(t, e, i, s) {
            P(t, e, i, s, !1);
        },
        one(t, e, i, s) {
            P(t, e, i, s, !0);
        },
        off(t, e, i, s) {
            if ("string" != typeof e || !t) return;
            const [n, o, r] = N(e, i, s),
                a = r !== e,
                l = S(t),
                c = l[r] || {},
                h = e.startsWith(".");
            if (void 0 === o) {
                if (h) for (const i of Object.keys(l)) M(t, l, i, e.slice(1));
                for (const i of Object.keys(c)) {
                    const s = i.replace(C, "");
                    if (!a || e.includes(s)) {
                        const e = c[i];
                        x(t, l, r, e.callable, e.delegationSelector);
                    }
                }
            } else {
                if (!Object.keys(c).length) return;
                x(t, l, r, o, n ? i : null);
            }
        },
        trigger(t, e, i) {
            if ("string" != typeof e || !t) return null;
            const s = f();
            let n = null,
                o = !0,
                r = !0,
                a = !1;
            e !== j(e) && s && ((n = s.Event(e, i)), s(t).trigger(n), (o = !n.isPropagationStopped()), (r = !n.isImmediatePropagationStopped()), (a = n.isDefaultPrevented()));
            let l = new Event(e, { bubbles: o, cancelable: !0 });
            return (l = F(l, i)), a && l.preventDefault(), r && t.dispatchEvent(l), l.defaultPrevented && n && n.preventDefault(), l;
        },
    };
    function F(t, e) {
        for (const [i, s] of Object.entries(e || {}))
            try {
                t[i] = s;
            } catch (e) {
                Object.defineProperty(t, i, { configurable: !0, get: () => s });
            }
        return t;
    }
    const z = new Map(),
        H = {
            set(t, e, i) {
                z.has(t) || z.set(t, new Map());
                const s = z.get(t);
                s.has(e) || 0 === s.size ? s.set(e, i) : console.error(`Bootstrap doesn't allow more than one instance per element. Bound instance: ${Array.from(s.keys())[0]}.`);
            },
            get: (t, e) => (z.has(t) && z.get(t).get(e)) || null,
            remove(t, e) {
                if (!z.has(t)) return;
                const i = z.get(t);
                i.delete(e), 0 === i.size && z.delete(t);
            },
        };
    function q(t) {
        if ("true" === t) return !0;
        if ("false" === t) return !1;
        if (t === Number(t).toString()) return Number(t);
        if ("" === t || "null" === t) return null;
        if ("string" != typeof t) return t;
        try {
            return JSON.parse(decodeURIComponent(t));
        } catch (e) {
            return t;
        }
    }
    function B(t) {
        return t.replace(/[A-Z]/g, (t) => `-${t.toLowerCase()}`);
    }
    const W = {
        setDataAttribute(t, e, i) {
            t.setAttribute(`data-bs-${B(e)}`, i);
        },
        removeDataAttribute(t, e) {
            t.removeAttribute(`data-bs-${B(e)}`);
        },
        getDataAttributes(t) {
            if (!t) return {};
            const e = {},
                i = Object.keys(t.dataset).filter((t) => t.startsWith("bs") && !t.startsWith("bsConfig"));
            for (const s of i) {
                let i = s.replace(/^bs/, "");
                (i = i.charAt(0).toLowerCase() + i.slice(1, i.length)), (e[i] = q(t.dataset[s]));
            }
            return e;
        },
        getDataAttribute: (t, e) => q(t.getAttribute(`data-bs-${B(e)}`)),
    };
    class R {
        static get Default() {
            return {};
        }
        static get DefaultType() {
            return {};
        }
        static get NAME() {
            throw new Error('You have to implement the static method "NAME", for each component!');
        }
        _getConfig(t) {
            return (t = this._mergeConfigObj(t)), (t = this._configAfterMerge(t)), this._typeCheckConfig(t), t;
        }
        _configAfterMerge(t) {
            return t;
        }
        _mergeConfigObj(t, e) {
            const i = l(e) ? W.getDataAttribute(e, "config") : {};
            return { ...this.constructor.Default, ...("object" == typeof i ? i : {}), ...(l(e) ? W.getDataAttributes(e) : {}), ...("object" == typeof t ? t : {}) };
        }
        _typeCheckConfig(t, e = this.constructor.DefaultType) {
            for (const s of Object.keys(e)) {
                const n = e[s],
                    o = t[s],
                    r = l(o)
                        ? "element"
                        : null == (i = o)
                        ? `${i}`
                        : Object.prototype.toString
                              .call(i)
                              .match(/\s([a-z]+)/i)[1]
                              .toLowerCase();
                if (!new RegExp(n).test(r)) throw new TypeError(`${this.constructor.NAME.toUpperCase()}: Option "${s}" provided type "${r}" but expected type "${n}".`);
            }
            var i;
        }
    }
    class V extends R {
        constructor(t, e) {
            super(), (t = c(t)) && ((this._element = t), (this._config = this._getConfig(e)), H.set(this._element, this.constructor.DATA_KEY, this));
        }
        dispose() {
            H.remove(this._element, this.constructor.DATA_KEY), $.off(this._element, this.constructor.EVENT_KEY);
            for (const t of Object.getOwnPropertyNames(this)) this[t] = null;
        }
        _queueCallback(t, e, i = !0) {
            y(t, e, i);
        }
        _getConfig(t) {
            return (t = this._mergeConfigObj(t, this._element)), (t = this._configAfterMerge(t)), this._typeCheckConfig(t), t;
        }
        static getInstance(t) {
            return H.get(c(t), this.DATA_KEY);
        }
        static getOrCreateInstance(t, e = {}) {
            return this.getInstance(t) || new this(t, "object" == typeof e ? e : null);
        }
        static get VERSION() {
            return "5.2.3";
        }
        static get DATA_KEY() {
            return `bs.${this.NAME}`;
        }
        static get EVENT_KEY() {
            return `.${this.DATA_KEY}`;
        }
        static eventName(t) {
            return `${t}${this.EVENT_KEY}`;
        }
    }
    const K = (t, e = "hide") => {
        const i = `click.dismiss${t.EVENT_KEY}`,
            s = t.NAME;
        $.on(document, i, `[data-bs-dismiss="${s}"]`, function (i) {
            if ((["A", "AREA"].includes(this.tagName) && i.preventDefault(), d(this))) return;
            const n = r(this) || this.closest(`.${s}`);
            t.getOrCreateInstance(n)[e]();
        });
    };
    class Q extends V {
        static get NAME() {
            return "alert";
        }
        close() {
            if ($.trigger(this._element, "close.bs.alert").defaultPrevented) return;
            this._element.classList.remove("show");
            const t = this._element.classList.contains("fade");
            this._queueCallback(() => this._destroyElement(), this._element, t);
        }
        _destroyElement() {
            this._element.remove(), $.trigger(this._element, "closed.bs.alert"), this.dispose();
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = Q.getOrCreateInstance(this);
                if ("string" == typeof t) {
                    if (void 0 === e[t] || t.startsWith("_") || "constructor" === t) throw new TypeError(`No method named "${t}"`);
                    e[t](this);
                }
            });
        }
    }
    K(Q, "close"), b(Q);
    const X = '[data-bs-toggle="button"]';
    class Y extends V {
        static get NAME() {
            return "button";
        }
        toggle() {
            this._element.setAttribute("aria-pressed", this._element.classList.toggle("active"));
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = Y.getOrCreateInstance(this);
                "toggle" === t && e[t]();
            });
        }
    }
    $.on(document, "click.bs.button.data-api", X, (t) => {
        t.preventDefault();
        const e = t.target.closest(X);
        Y.getOrCreateInstance(e).toggle();
    }),
        b(Y);
    const U = {
            find: (t, e = document.documentElement) => [].concat(...Element.prototype.querySelectorAll.call(e, t)),
            findOne: (t, e = document.documentElement) => Element.prototype.querySelector.call(e, t),
            children: (t, e) => [].concat(...t.children).filter((t) => t.matches(e)),
            parents(t, e) {
                const i = [];
                let s = t.parentNode.closest(e);
                for (; s; ) i.push(s), (s = s.parentNode.closest(e));
                return i;
            },
            prev(t, e) {
                let i = t.previousElementSibling;
                for (; i; ) {
                    if (i.matches(e)) return [i];
                    i = i.previousElementSibling;
                }
                return [];
            },
            next(t, e) {
                let i = t.nextElementSibling;
                for (; i; ) {
                    if (i.matches(e)) return [i];
                    i = i.nextElementSibling;
                }
                return [];
            },
            focusableChildren(t) {
                const e = ["a", "button", "input", "textarea", "select", "details", "[tabindex]", '[contenteditable="true"]'].map((t) => `${t}:not([tabindex^="-"])`).join(",");
                return this.find(e, t).filter((t) => !d(t) && h(t));
            },
        },
        G = { endCallback: null, leftCallback: null, rightCallback: null },
        J = { endCallback: "(function|null)", leftCallback: "(function|null)", rightCallback: "(function|null)" };
    class Z extends R {
        constructor(t, e) {
            super(), (this._element = t), t && Z.isSupported() && ((this._config = this._getConfig(e)), (this._deltaX = 0), (this._supportPointerEvents = Boolean(window.PointerEvent)), this._initEvents());
        }
        static get Default() {
            return G;
        }
        static get DefaultType() {
            return J;
        }
        static get NAME() {
            return "swipe";
        }
        dispose() {
            $.off(this._element, ".bs.swipe");
        }
        _start(t) {
            this._supportPointerEvents ? this._eventIsPointerPenTouch(t) && (this._deltaX = t.clientX) : (this._deltaX = t.touches[0].clientX);
        }
        _end(t) {
            this._eventIsPointerPenTouch(t) && (this._deltaX = t.clientX - this._deltaX), this._handleSwipe(), v(this._config.endCallback);
        }
        _move(t) {
            this._deltaX = t.touches && t.touches.length > 1 ? 0 : t.touches[0].clientX - this._deltaX;
        }
        _handleSwipe() {
            const t = Math.abs(this._deltaX);
            if (t <= 40) return;
            const e = t / this._deltaX;
            (this._deltaX = 0), e && v(e > 0 ? this._config.rightCallback : this._config.leftCallback);
        }
        _initEvents() {
            this._supportPointerEvents
                ? ($.on(this._element, "pointerdown.bs.swipe", (t) => this._start(t)), $.on(this._element, "pointerup.bs.swipe", (t) => this._end(t)), this._element.classList.add("pointer-event"))
                : ($.on(this._element, "touchstart.bs.swipe", (t) => this._start(t)), $.on(this._element, "touchmove.bs.swipe", (t) => this._move(t)), $.on(this._element, "touchend.bs.swipe", (t) => this._end(t)));
        }
        _eventIsPointerPenTouch(t) {
            return this._supportPointerEvents && ("pen" === t.pointerType || "touch" === t.pointerType);
        }
        static isSupported() {
            return "ontouchstart" in document.documentElement || navigator.maxTouchPoints > 0;
        }
    }
    const tt = "next",
        et = "prev",
        it = "left",
        st = "right",
        nt = "slid.bs.carousel",
        ot = "carousel",
        rt = "active",
        at = { ArrowLeft: st, ArrowRight: it },
        lt = { interval: 5e3, keyboard: !0, pause: "hover", ride: !1, touch: !0, wrap: !0 },
        ct = { interval: "(number|boolean)", keyboard: "boolean", pause: "(string|boolean)", ride: "(boolean|string)", touch: "boolean", wrap: "boolean" };
    class ht extends V {
        constructor(t, e) {
            super(t, e),
                (this._interval = null),
                (this._activeElement = null),
                (this._isSliding = !1),
                (this.touchTimeout = null),
                (this._swipeHelper = null),
                (this._indicatorsElement = U.findOne(".carousel-indicators", this._element)),
                this._addEventListeners(),
                this._config.ride === ot && this.cycle();
        }
        static get Default() {
            return lt;
        }
        static get DefaultType() {
            return ct;
        }
        static get NAME() {
            return "carousel";
        }
        next() {
            this._slide(tt);
        }
        nextWhenVisible() {
            !document.hidden && h(this._element) && this.next();
        }
        prev() {
            this._slide(et);
        }
        pause() {
            this._isSliding && a(this._element), this._clearInterval();
        }
        cycle() {
            this._clearInterval(), this._updateInterval(), (this._interval = setInterval(() => this.nextWhenVisible(), this._config.interval));
        }
        _maybeEnableCycle() {
            this._config.ride && (this._isSliding ? $.one(this._element, nt, () => this.cycle()) : this.cycle());
        }
        to(t) {
            const e = this._getItems();
            if (t > e.length - 1 || t < 0) return;
            if (this._isSliding) return void $.one(this._element, nt, () => this.to(t));
            const i = this._getItemIndex(this._getActive());
            if (i === t) return;
            const s = t > i ? tt : et;
            this._slide(s, e[t]);
        }
        dispose() {
            this._swipeHelper && this._swipeHelper.dispose(), super.dispose();
        }
        _configAfterMerge(t) {
            return (t.defaultInterval = t.interval), t;
        }
        _addEventListeners() {
            this._config.keyboard && $.on(this._element, "keydown.bs.carousel", (t) => this._keydown(t)),
                "hover" === this._config.pause && ($.on(this._element, "mouseenter.bs.carousel", () => this.pause()), $.on(this._element, "mouseleave.bs.carousel", () => this._maybeEnableCycle())),
                this._config.touch && Z.isSupported() && this._addTouchEventListeners();
        }
        _addTouchEventListeners() {
            for (const t of U.find(".carousel-item img", this._element)) $.on(t, "dragstart.bs.carousel", (t) => t.preventDefault());
            const t = {
                leftCallback: () => this._slide(this._directionToOrder(it)),
                rightCallback: () => this._slide(this._directionToOrder(st)),
                endCallback: () => {
                    "hover" === this._config.pause && (this.pause(), this.touchTimeout && clearTimeout(this.touchTimeout), (this.touchTimeout = setTimeout(() => this._maybeEnableCycle(), 500 + this._config.interval)));
                },
            };
            this._swipeHelper = new Z(this._element, t);
        }
        _keydown(t) {
            if (/input|textarea/i.test(t.target.tagName)) return;
            const e = at[t.key];
            e && (t.preventDefault(), this._slide(this._directionToOrder(e)));
        }
        _getItemIndex(t) {
            return this._getItems().indexOf(t);
        }
        _setActiveIndicatorElement(t) {
            if (!this._indicatorsElement) return;
            const e = U.findOne(".active", this._indicatorsElement);
            e.classList.remove(rt), e.removeAttribute("aria-current");
            const i = U.findOne(`[data-bs-slide-to="${t}"]`, this._indicatorsElement);
            i && (i.classList.add(rt), i.setAttribute("aria-current", "true"));
        }
        _updateInterval() {
            const t = this._activeElement || this._getActive();
            if (!t) return;
            const e = Number.parseInt(t.getAttribute("data-bs-interval"), 10);
            this._config.interval = e || this._config.defaultInterval;
        }
        _slide(t, e = null) {
            if (this._isSliding) return;
            const i = this._getActive(),
                s = t === tt,
                n = e || w(this._getItems(), i, s, this._config.wrap);
            if (n === i) return;
            const o = this._getItemIndex(n),
                r = (e) => $.trigger(this._element, e, { relatedTarget: n, direction: this._orderToDirection(t), from: this._getItemIndex(i), to: o });
            if (r("slide.bs.carousel").defaultPrevented) return;
            if (!i || !n) return;
            const a = Boolean(this._interval);
            this.pause(), (this._isSliding = !0), this._setActiveIndicatorElement(o), (this._activeElement = n);
            const l = s ? "carousel-item-start" : "carousel-item-end",
                c = s ? "carousel-item-next" : "carousel-item-prev";
            n.classList.add(c),
                g(n),
                i.classList.add(l),
                n.classList.add(l),
                this._queueCallback(
                    () => {
                        n.classList.remove(l, c), n.classList.add(rt), i.classList.remove(rt, c, l), (this._isSliding = !1), r(nt);
                    },
                    i,
                    this._isAnimated()
                ),
                a && this.cycle();
        }
        _isAnimated() {
            return this._element.classList.contains("slide");
        }
        _getActive() {
            return U.findOne(".active.carousel-item", this._element);
        }
        _getItems() {
            return U.find(".carousel-item", this._element);
        }
        _clearInterval() {
            this._interval && (clearInterval(this._interval), (this._interval = null));
        }
        _directionToOrder(t) {
            return m() ? (t === it ? et : tt) : t === it ? tt : et;
        }
        _orderToDirection(t) {
            return m() ? (t === et ? it : st) : t === et ? st : it;
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = ht.getOrCreateInstance(this, t);
                if ("number" != typeof t) {
                    if ("string" == typeof t) {
                        if (void 0 === e[t] || t.startsWith("_") || "constructor" === t) throw new TypeError(`No method named "${t}"`);
                        e[t]();
                    }
                } else e.to(t);
            });
        }
    }
    $.on(document, "click.bs.carousel.data-api", "[data-bs-slide], [data-bs-slide-to]", function (t) {
        const e = r(this);
        if (!e || !e.classList.contains(ot)) return;
        t.preventDefault();
        const i = ht.getOrCreateInstance(e),
            s = this.getAttribute("data-bs-slide-to");
        return s ? (i.to(s), void i._maybeEnableCycle()) : "next" === W.getDataAttribute(this, "slide") ? (i.next(), void i._maybeEnableCycle()) : (i.prev(), void i._maybeEnableCycle());
    }),
        $.on(window, "load.bs.carousel.data-api", () => {
            const t = U.find('[data-bs-ride="carousel"]');
            for (const e of t) ht.getOrCreateInstance(e);
        }),
        b(ht);
    const dt = "show",
        ut = "collapse",
        _t = "collapsing",
        gt = '[data-bs-toggle="collapse"]',
        ft = { parent: null, toggle: !0 },
        pt = { parent: "(null|element)", toggle: "boolean" };
    class mt extends V {
        constructor(t, e) {
            super(t, e), (this._isTransitioning = !1), (this._triggerArray = []);
            const i = U.find(gt);
            for (const t of i) {
                const e = o(t),
                    i = U.find(e).filter((t) => t === this._element);
                null !== e && i.length && this._triggerArray.push(t);
            }
            this._initializeChildren(), this._config.parent || this._addAriaAndCollapsedClass(this._triggerArray, this._isShown()), this._config.toggle && this.toggle();
        }
        static get Default() {
            return ft;
        }
        static get DefaultType() {
            return pt;
        }
        static get NAME() {
            return "collapse";
        }
        toggle() {
            this._isShown() ? this.hide() : this.show();
        }
        show() {
            if (this._isTransitioning || this._isShown()) return;
            let t = [];
            if (
                (this._config.parent &&
                    (t = this._getFirstLevelChildren(".collapse.show, .collapse.collapsing")
                        .filter((t) => t !== this._element)
                        .map((t) => mt.getOrCreateInstance(t, { toggle: !1 }))),
                t.length && t[0]._isTransitioning)
            )
                return;
            if ($.trigger(this._element, "show.bs.collapse").defaultPrevented) return;
            for (const e of t) e.hide();
            const e = this._getDimension();
            this._element.classList.remove(ut), this._element.classList.add(_t), (this._element.style[e] = 0), this._addAriaAndCollapsedClass(this._triggerArray, !0), (this._isTransitioning = !0);
            const i = `scroll${e[0].toUpperCase() + e.slice(1)}`;
            this._queueCallback(
                () => {
                    (this._isTransitioning = !1), this._element.classList.remove(_t), this._element.classList.add(ut, dt), (this._element.style[e] = ""), $.trigger(this._element, "shown.bs.collapse");
                },
                this._element,
                !0
            ),
                (this._element.style[e] = `${this._element[i]}px`);
        }
        hide() {
            if (this._isTransitioning || !this._isShown()) return;
            if ($.trigger(this._element, "hide.bs.collapse").defaultPrevented) return;
            const t = this._getDimension();
            (this._element.style[t] = `${this._element.getBoundingClientRect()[t]}px`), g(this._element), this._element.classList.add(_t), this._element.classList.remove(ut, dt);
            for (const t of this._triggerArray) {
                const e = r(t);
                e && !this._isShown(e) && this._addAriaAndCollapsedClass([t], !1);
            }
            (this._isTransitioning = !0),
                (this._element.style[t] = ""),
                this._queueCallback(
                    () => {
                        (this._isTransitioning = !1), this._element.classList.remove(_t), this._element.classList.add(ut), $.trigger(this._element, "hidden.bs.collapse");
                    },
                    this._element,
                    !0
                );
        }
        _isShown(t = this._element) {
            return t.classList.contains(dt);
        }
        _configAfterMerge(t) {
            return (t.toggle = Boolean(t.toggle)), (t.parent = c(t.parent)), t;
        }
        _getDimension() {
            return this._element.classList.contains("collapse-horizontal") ? "width" : "height";
        }
        _initializeChildren() {
            if (!this._config.parent) return;
            const t = this._getFirstLevelChildren(gt);
            for (const e of t) {
                const t = r(e);
                t && this._addAriaAndCollapsedClass([e], this._isShown(t));
            }
        }
        _getFirstLevelChildren(t) {
            const e = U.find(":scope .collapse .collapse", this._config.parent);
            return U.find(t, this._config.parent).filter((t) => !e.includes(t));
        }
        _addAriaAndCollapsedClass(t, e) {
            if (t.length) for (const i of t) i.classList.toggle("collapsed", !e), i.setAttribute("aria-expanded", e);
        }
        static jQueryInterface(t) {
            const e = {};
            return (
                "string" == typeof t && /show|hide/.test(t) && (e.toggle = !1),
                this.each(function () {
                    const i = mt.getOrCreateInstance(this, e);
                    if ("string" == typeof t) {
                        if (void 0 === i[t]) throw new TypeError(`No method named "${t}"`);
                        i[t]();
                    }
                })
            );
        }
    }
    $.on(document, "click.bs.collapse.data-api", gt, function (t) {
        ("A" === t.target.tagName || (t.delegateTarget && "A" === t.delegateTarget.tagName)) && t.preventDefault();
        const e = o(this),
            i = U.find(e);
        for (const t of i) mt.getOrCreateInstance(t, { toggle: !1 }).toggle();
    }),
        b(mt);
    const bt = "dropdown",
        vt = "ArrowUp",
        yt = "ArrowDown",
        wt = "click.bs.dropdown.data-api",
        At = "keydown.bs.dropdown.data-api",
        Et = "show",
        Ct = '[data-bs-toggle="dropdown"]:not(.disabled):not(:disabled)',
        Tt = `${Ct}.show`,
        kt = ".dropdown-menu",
        Lt = m() ? "top-end" : "top-start",
        Ot = m() ? "top-start" : "top-end",
        It = m() ? "bottom-end" : "bottom-start",
        St = m() ? "bottom-start" : "bottom-end",
        Dt = m() ? "left-start" : "right-start",
        Nt = m() ? "right-start" : "left-start",
        Pt = { autoClose: !0, boundary: "clippingParents", display: "dynamic", offset: [0, 2], popperConfig: null, reference: "toggle" },
        xt = { autoClose: "(boolean|string)", boundary: "(string|element)", display: "string", offset: "(array|string|function)", popperConfig: "(null|object|function)", reference: "(string|element|object)" };
    class Mt extends V {
        constructor(t, e) {
            super(t, e), (this._popper = null), (this._parent = this._element.parentNode), (this._menu = U.next(this._element, kt)[0] || U.prev(this._element, kt)[0] || U.findOne(kt, this._parent)), (this._inNavbar = this._detectNavbar());
        }
        static get Default() {
            return Pt;
        }
        static get DefaultType() {
            return xt;
        }
        static get NAME() {
            return bt;
        }
        toggle() {
            return this._isShown() ? this.hide() : this.show();
        }
        show() {
            if (d(this._element) || this._isShown()) return;
            const t = { relatedTarget: this._element };
            if (!$.trigger(this._element, "show.bs.dropdown", t).defaultPrevented) {
                if ((this._createPopper(), "ontouchstart" in document.documentElement && !this._parent.closest(".navbar-nav"))) for (const t of [].concat(...document.body.children)) $.on(t, "mouseover", _);
                this._element.focus(), this._element.setAttribute("aria-expanded", !0), this._menu.classList.add(Et), this._element.classList.add(Et), $.trigger(this._element, "shown.bs.dropdown", t);
            }
        }
        hide() {
            if (d(this._element) || !this._isShown()) return;
            const t = { relatedTarget: this._element };
            this._completeHide(t);
        }
        dispose() {
            this._popper && this._popper.destroy(), super.dispose();
        }
        update() {
            (this._inNavbar = this._detectNavbar()), this._popper && this._popper.update();
        }
        _completeHide(t) {
            if (!$.trigger(this._element, "hide.bs.dropdown", t).defaultPrevented) {
                if ("ontouchstart" in document.documentElement) for (const t of [].concat(...document.body.children)) $.off(t, "mouseover", _);
                this._popper && this._popper.destroy(),
                    this._menu.classList.remove(Et),
                    this._element.classList.remove(Et),
                    this._element.setAttribute("aria-expanded", "false"),
                    W.removeDataAttribute(this._menu, "popper"),
                    $.trigger(this._element, "hidden.bs.dropdown", t);
            }
        }
        _getConfig(t) {
            if ("object" == typeof (t = super._getConfig(t)).reference && !l(t.reference) && "function" != typeof t.reference.getBoundingClientRect)
                throw new TypeError(`${bt.toUpperCase()}: Option "reference" provided type "object" without a required "getBoundingClientRect" method.`);
            return t;
        }
        _createPopper() {
            if (void 0 === i) throw new TypeError("Bootstrap's dropdowns require Popper (https://popper.js.org)");
            let t = this._element;
            "parent" === this._config.reference ? (t = this._parent) : l(this._config.reference) ? (t = c(this._config.reference)) : "object" == typeof this._config.reference && (t = this._config.reference);
            const e = this._getPopperConfig();
            this._popper = i.createPopper(t, this._menu, e);
        }
        _isShown() {
            return this._menu.classList.contains(Et);
        }
        _getPlacement() {
            const t = this._parent;
            if (t.classList.contains("dropend")) return Dt;
            if (t.classList.contains("dropstart")) return Nt;
            if (t.classList.contains("dropup-center")) return "top";
            if (t.classList.contains("dropdown-center")) return "bottom";
            const e = "end" === getComputedStyle(this._menu).getPropertyValue("--bs-position").trim();
            return t.classList.contains("dropup") ? (e ? Ot : Lt) : e ? St : It;
        }
        _detectNavbar() {
            return null !== this._element.closest(".navbar");
        }
        _getOffset() {
            const { offset: t } = this._config;
            return "string" == typeof t ? t.split(",").map((t) => Number.parseInt(t, 10)) : "function" == typeof t ? (e) => t(e, this._element) : t;
        }
        _getPopperConfig() {
            const t = {
                placement: this._getPlacement(),
                modifiers: [
                    { name: "preventOverflow", options: { boundary: this._config.boundary } },
                    { name: "offset", options: { offset: this._getOffset() } },
                ],
            };
            return (
                (this._inNavbar || "static" === this._config.display) && (W.setDataAttribute(this._menu, "popper", "static"), (t.modifiers = [{ name: "applyStyles", enabled: !1 }])),
                { ...t, ...("function" == typeof this._config.popperConfig ? this._config.popperConfig(t) : this._config.popperConfig) }
            );
        }
        _selectMenuItem({ key: t, target: e }) {
            const i = U.find(".dropdown-menu .dropdown-item:not(.disabled):not(:disabled)", this._menu).filter((t) => h(t));
            i.length && w(i, e, t === yt, !i.includes(e)).focus();
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = Mt.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === e[t]) throw new TypeError(`No method named "${t}"`);
                    e[t]();
                }
            });
        }
        static clearMenus(t) {
            if (2 === t.button || ("keyup" === t.type && "Tab" !== t.key)) return;
            const e = U.find(Tt);
            for (const i of e) {
                const e = Mt.getInstance(i);
                if (!e || !1 === e._config.autoClose) continue;
                const s = t.composedPath(),
                    n = s.includes(e._menu);
                if (s.includes(e._element) || ("inside" === e._config.autoClose && !n) || ("outside" === e._config.autoClose && n)) continue;
                if (e._menu.contains(t.target) && (("keyup" === t.type && "Tab" === t.key) || /input|select|option|textarea|form/i.test(t.target.tagName))) continue;
                const o = { relatedTarget: e._element };
                "click" === t.type && (o.clickEvent = t), e._completeHide(o);
            }
        }
        static dataApiKeydownHandler(t) {
            const e = /input|textarea/i.test(t.target.tagName),
                i = "Escape" === t.key,
                s = [vt, yt].includes(t.key);
            if (!s && !i) return;
            if (e && !i) return;
            t.preventDefault();
            const n = this.matches(Ct) ? this : U.prev(this, Ct)[0] || U.next(this, Ct)[0] || U.findOne(Ct, t.delegateTarget.parentNode),
                o = Mt.getOrCreateInstance(n);
            if (s) return t.stopPropagation(), o.show(), void o._selectMenuItem(t);
            o._isShown() && (t.stopPropagation(), o.hide(), n.focus());
        }
    }
    $.on(document, At, Ct, Mt.dataApiKeydownHandler),
        $.on(document, At, kt, Mt.dataApiKeydownHandler),
        $.on(document, wt, Mt.clearMenus),
        $.on(document, "keyup.bs.dropdown.data-api", Mt.clearMenus),
        $.on(document, wt, Ct, function (t) {
            t.preventDefault(), Mt.getOrCreateInstance(this).toggle();
        }),
        b(Mt);
    const jt = ".fixed-top, .fixed-bottom, .is-fixed, .sticky-top",
        $t = ".sticky-top",
        Ft = "padding-right",
        zt = "margin-right";
    class Ht {
        constructor() {
            this._element = document.body;
        }
        getWidth() {
            const t = document.documentElement.clientWidth;
            return Math.abs(window.innerWidth - t);
        }
        hide() {
            const t = this.getWidth();
            this._disableOverFlow(), this._setElementAttributes(this._element, Ft, (e) => e + t), this._setElementAttributes(jt, Ft, (e) => e + t), this._setElementAttributes($t, zt, (e) => e - t);
        }
        reset() {
            this._resetElementAttributes(this._element, "overflow"), this._resetElementAttributes(this._element, Ft), this._resetElementAttributes(jt, Ft), this._resetElementAttributes($t, zt);
        }
        isOverflowing() {
            return this.getWidth() > 0;
        }
        _disableOverFlow() {
            this._saveInitialAttribute(this._element, "overflow"), (this._element.style.overflow = "hidden");
        }
        _setElementAttributes(t, e, i) {
            const s = this.getWidth();
            this._applyManipulationCallback(t, (t) => {
                if (t !== this._element && window.innerWidth > t.clientWidth + s) return;
                this._saveInitialAttribute(t, e);
                const n = window.getComputedStyle(t).getPropertyValue(e);
                t.style.setProperty(e, `${i(Number.parseFloat(n))}px`);
            });
        }
        _saveInitialAttribute(t, e) {
            const i = t.style.getPropertyValue(e);
            i && W.setDataAttribute(t, e, i);
        }
        _resetElementAttributes(t, e) {
            this._applyManipulationCallback(t, (t) => {
                const i = W.getDataAttribute(t, e);
                null !== i ? (W.removeDataAttribute(t, e), t.style.setProperty(e, i)) : t.style.removeProperty(e);
            });
        }
        _applyManipulationCallback(t, e) {
            if (l(t)) e(t);
            else for (const i of U.find(t, this._element)) e(i);
        }
    }
    const qt = "show",
        Bt = "mousedown.bs.backdrop",
        Wt = { className: "modal-backdrop", clickCallback: null, isAnimated: !1, isVisible: !0, rootElement: "body" },
        Rt = { className: "string", clickCallback: "(function|null)", isAnimated: "boolean", isVisible: "boolean", rootElement: "(element|string)" };
    class Vt extends R {
        constructor(t) {
            super(), (this._config = this._getConfig(t)), (this._isAppended = !1), (this._element = null);
        }
        static get Default() {
            return Wt;
        }
        static get DefaultType() {
            return Rt;
        }
        static get NAME() {
            return "backdrop";
        }
        show(t) {
            if (!this._config.isVisible) return void v(t);
            this._append();
            const e = this._getElement();
            this._config.isAnimated && g(e),
                e.classList.add(qt),
                this._emulateAnimation(() => {
                    v(t);
                });
        }
        hide(t) {
            this._config.isVisible
                ? (this._getElement().classList.remove(qt),
                  this._emulateAnimation(() => {
                      this.dispose(), v(t);
                  }))
                : v(t);
        }
        dispose() {
            this._isAppended && ($.off(this._element, Bt), this._element.remove(), (this._isAppended = !1));
        }
        _getElement() {
            if (!this._element) {
                const t = document.createElement("div");
                (t.className = this._config.className), this._config.isAnimated && t.classList.add("fade"), (this._element = t);
            }
            return this._element;
        }
        _configAfterMerge(t) {
            return (t.rootElement = c(t.rootElement)), t;
        }
        _append() {
            if (this._isAppended) return;
            const t = this._getElement();
            this._config.rootElement.append(t),
                $.on(t, Bt, () => {
                    v(this._config.clickCallback);
                }),
                (this._isAppended = !0);
        }
        _emulateAnimation(t) {
            y(t, this._getElement(), this._config.isAnimated);
        }
    }
    const Kt = ".bs.focustrap",
        Qt = "backward",
        Xt = { autofocus: !0, trapElement: null },
        Yt = { autofocus: "boolean", trapElement: "element" };
    class Ut extends R {
        constructor(t) {
            super(), (this._config = this._getConfig(t)), (this._isActive = !1), (this._lastTabNavDirection = null);
        }
        static get Default() {
            return Xt;
        }
        static get DefaultType() {
            return Yt;
        }
        static get NAME() {
            return "focustrap";
        }
        activate() {
            this._isActive ||
                (this._config.autofocus && this._config.trapElement.focus(),
                $.off(document, Kt),
                $.on(document, "focusin.bs.focustrap", (t) => this._handleFocusin(t)),
                $.on(document, "keydown.tab.bs.focustrap", (t) => this._handleKeydown(t)),
                (this._isActive = !0));
        }
        deactivate() {
            this._isActive && ((this._isActive = !1), $.off(document, Kt));
        }
        _handleFocusin(t) {
            const { trapElement: e } = this._config;
            if (t.target === document || t.target === e || e.contains(t.target)) return;
            const i = U.focusableChildren(e);
            0 === i.length ? e.focus() : this._lastTabNavDirection === Qt ? i[i.length - 1].focus() : i[0].focus();
        }
        _handleKeydown(t) {
            "Tab" === t.key && (this._lastTabNavDirection = t.shiftKey ? Qt : "forward");
        }
    }
    const Gt = "hidden.bs.modal",
        Jt = "show.bs.modal",
        Zt = "modal-open",
        te = "show",
        ee = "modal-static",
        ie = { backdrop: !0, focus: !0, keyboard: !0 },
        se = { backdrop: "(boolean|string)", focus: "boolean", keyboard: "boolean" };
    class ne extends V {
        constructor(t, e) {
            super(t, e),
                (this._dialog = U.findOne(".modal-dialog", this._element)),
                (this._backdrop = this._initializeBackDrop()),
                (this._focustrap = this._initializeFocusTrap()),
                (this._isShown = !1),
                (this._isTransitioning = !1),
                (this._scrollBar = new Ht()),
                this._addEventListeners();
        }
        static get Default() {
            return ie;
        }
        static get DefaultType() {
            return se;
        }
        static get NAME() {
            return "modal";
        }
        toggle(t) {
            return this._isShown ? this.hide() : this.show(t);
        }
        show(t) {
            this._isShown ||
                this._isTransitioning ||
                $.trigger(this._element, Jt, { relatedTarget: t }).defaultPrevented ||
                ((this._isShown = !0), (this._isTransitioning = !0), this._scrollBar.hide(), document.body.classList.add(Zt), this._adjustDialog(), this._backdrop.show(() => this._showElement(t)));
        }
        hide() {
            this._isShown &&
                !this._isTransitioning &&
                ($.trigger(this._element, "hide.bs.modal").defaultPrevented ||
                    ((this._isShown = !1), (this._isTransitioning = !0), this._focustrap.deactivate(), this._element.classList.remove(te), this._queueCallback(() => this._hideModal(), this._element, this._isAnimated())));
        }
        dispose() {
            for (const t of [window, this._dialog]) $.off(t, ".bs.modal");
            this._backdrop.dispose(), this._focustrap.deactivate(), super.dispose();
        }
        handleUpdate() {
            this._adjustDialog();
        }
        _initializeBackDrop() {
            return new Vt({ isVisible: Boolean(this._config.backdrop), isAnimated: this._isAnimated() });
        }
        _initializeFocusTrap() {
            return new Ut({ trapElement: this._element });
        }
        _showElement(t) {
            document.body.contains(this._element) || document.body.append(this._element),
                (this._element.style.display = "block"),
                this._element.removeAttribute("aria-hidden"),
                this._element.setAttribute("aria-modal", !0),
                this._element.setAttribute("role", "dialog"),
                (this._element.scrollTop = 0);
            const e = U.findOne(".modal-body", this._dialog);
            e && (e.scrollTop = 0),
                g(this._element),
                this._element.classList.add(te),
                this._queueCallback(
                    () => {
                        this._config.focus && this._focustrap.activate(), (this._isTransitioning = !1), $.trigger(this._element, "shown.bs.modal", { relatedTarget: t });
                    },
                    this._dialog,
                    this._isAnimated()
                );
        }
        _addEventListeners() {
            $.on(this._element, "keydown.dismiss.bs.modal", (t) => {
                if ("Escape" === t.key) return this._config.keyboard ? (t.preventDefault(), void this.hide()) : void this._triggerBackdropTransition();
            }),
                $.on(window, "resize.bs.modal", () => {
                    this._isShown && !this._isTransitioning && this._adjustDialog();
                }),
                $.on(this._element, "mousedown.dismiss.bs.modal", (t) => {
                    $.one(this._element, "click.dismiss.bs.modal", (e) => {
                        this._element === t.target && this._element === e.target && ("static" !== this._config.backdrop ? this._config.backdrop && this.hide() : this._triggerBackdropTransition());
                    });
                });
        }
        _hideModal() {
            (this._element.style.display = "none"),
                this._element.setAttribute("aria-hidden", !0),
                this._element.removeAttribute("aria-modal"),
                this._element.removeAttribute("role"),
                (this._isTransitioning = !1),
                this._backdrop.hide(() => {
                    document.body.classList.remove(Zt), this._resetAdjustments(), this._scrollBar.reset(), $.trigger(this._element, Gt);
                });
        }
        _isAnimated() {
            return this._element.classList.contains("fade");
        }
        _triggerBackdropTransition() {
            if ($.trigger(this._element, "hidePrevented.bs.modal").defaultPrevented) return;
            const t = this._element.scrollHeight > document.documentElement.clientHeight,
                e = this._element.style.overflowY;
            "hidden" === e ||
                this._element.classList.contains(ee) ||
                (t || (this._element.style.overflowY = "hidden"),
                this._element.classList.add(ee),
                this._queueCallback(() => {
                    this._element.classList.remove(ee),
                        this._queueCallback(() => {
                            this._element.style.overflowY = e;
                        }, this._dialog);
                }, this._dialog),
                this._element.focus());
        }
        _adjustDialog() {
            const t = this._element.scrollHeight > document.documentElement.clientHeight,
                e = this._scrollBar.getWidth(),
                i = e > 0;
            if (i && !t) {
                const t = m() ? "paddingLeft" : "paddingRight";
                this._element.style[t] = `${e}px`;
            }
            if (!i && t) {
                const t = m() ? "paddingRight" : "paddingLeft";
                this._element.style[t] = `${e}px`;
            }
        }
        _resetAdjustments() {
            (this._element.style.paddingLeft = ""), (this._element.style.paddingRight = "");
        }
        static jQueryInterface(t, e) {
            return this.each(function () {
                const i = ne.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === i[t]) throw new TypeError(`No method named "${t}"`);
                    i[t](e);
                }
            });
        }
    }
    $.on(document, "click.bs.modal.data-api", '[data-bs-toggle="modal"]', function (t) {
        const e = r(this);
        ["A", "AREA"].includes(this.tagName) && t.preventDefault(),
            $.one(e, Jt, (t) => {
                t.defaultPrevented ||
                    $.one(e, Gt, () => {
                        h(this) && this.focus();
                    });
            });
        const i = U.findOne(".modal.show");
        i && ne.getInstance(i).hide(), ne.getOrCreateInstance(e).toggle(this);
    }),
        K(ne),
        b(ne);
    const oe = "show",
        re = "showing",
        ae = "hiding",
        le = ".offcanvas.show",
        ce = "hidePrevented.bs.offcanvas",
        he = "hidden.bs.offcanvas",
        de = { backdrop: !0, keyboard: !0, scroll: !1 },
        ue = { backdrop: "(boolean|string)", keyboard: "boolean", scroll: "boolean" };
    class _e extends V {
        constructor(t, e) {
            super(t, e), (this._isShown = !1), (this._backdrop = this._initializeBackDrop()), (this._focustrap = this._initializeFocusTrap()), this._addEventListeners();
        }
        static get Default() {
            return de;
        }
        static get DefaultType() {
            return ue;
        }
        static get NAME() {
            return "offcanvas";
        }
        toggle(t) {
            return this._isShown ? this.hide() : this.show(t);
        }
        show(t) {
            this._isShown ||
                $.trigger(this._element, "show.bs.offcanvas", { relatedTarget: t }).defaultPrevented ||
                ((this._isShown = !0),
                this._backdrop.show(),
                this._config.scroll || new Ht().hide(),
                this._element.setAttribute("aria-modal", !0),
                this._element.setAttribute("role", "dialog"),
                this._element.classList.add(re),
                this._queueCallback(
                    () => {
                        (this._config.scroll && !this._config.backdrop) || this._focustrap.activate(),
                            this._element.classList.add(oe),
                            this._element.classList.remove(re),
                            $.trigger(this._element, "shown.bs.offcanvas", { relatedTarget: t });
                    },
                    this._element,
                    !0
                ));
        }
        hide() {
            this._isShown &&
                ($.trigger(this._element, "hide.bs.offcanvas").defaultPrevented ||
                    (this._focustrap.deactivate(),
                    this._element.blur(),
                    (this._isShown = !1),
                    this._element.classList.add(ae),
                    this._backdrop.hide(),
                    this._queueCallback(
                        () => {
                            this._element.classList.remove(oe, ae), this._element.removeAttribute("aria-modal"), this._element.removeAttribute("role"), this._config.scroll || new Ht().reset(), $.trigger(this._element, he);
                        },
                        this._element,
                        !0
                    )));
        }
        dispose() {
            this._backdrop.dispose(), this._focustrap.deactivate(), super.dispose();
        }
        _initializeBackDrop() {
            const t = Boolean(this._config.backdrop);
            return new Vt({
                className: "offcanvas-backdrop",
                isVisible: t,
                isAnimated: !0,
                rootElement: this._element.parentNode,
                clickCallback: t
                    ? () => {
                          "static" !== this._config.backdrop ? this.hide() : $.trigger(this._element, ce);
                      }
                    : null,
            });
        }
        _initializeFocusTrap() {
            return new Ut({ trapElement: this._element });
        }
        _addEventListeners() {
            $.on(this._element, "keydown.dismiss.bs.offcanvas", (t) => {
                "Escape" === t.key && (this._config.keyboard ? this.hide() : $.trigger(this._element, ce));
            });
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = _e.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === e[t] || t.startsWith("_") || "constructor" === t) throw new TypeError(`No method named "${t}"`);
                    e[t](this);
                }
            });
        }
    }
    $.on(document, "click.bs.offcanvas.data-api", '[data-bs-toggle="offcanvas"]', function (t) {
        const e = r(this);
        if ((["A", "AREA"].includes(this.tagName) && t.preventDefault(), d(this))) return;
        $.one(e, he, () => {
            h(this) && this.focus();
        });
        const i = U.findOne(le);
        i && i !== e && _e.getInstance(i).hide(), _e.getOrCreateInstance(e).toggle(this);
    }),
        $.on(window, "load.bs.offcanvas.data-api", () => {
            for (const t of U.find(le)) _e.getOrCreateInstance(t).show();
        }),
        $.on(window, "resize.bs.offcanvas", () => {
            for (const t of U.find("[aria-modal][class*=show][class*=offcanvas-]")) "fixed" !== getComputedStyle(t).position && _e.getOrCreateInstance(t).hide();
        }),
        K(_e),
        b(_e);
    const ge = new Set(["background", "cite", "href", "itemtype", "longdesc", "poster", "src", "xlink:href"]),
        fe = /^(?:(?:https?|mailto|ftp|tel|file|sms):|[^#&/:?]*(?:[#/?]|$))/i,
        pe = /^data:(?:image\/(?:bmp|gif|jpeg|jpg|png|tiff|webp)|video\/(?:mpeg|mp4|ogg|webm)|audio\/(?:mp3|oga|ogg|opus));base64,[\d+/a-z]+=*$/i,
        me = (t, e) => {
            const i = t.nodeName.toLowerCase();
            return e.includes(i) ? !ge.has(i) || Boolean(fe.test(t.nodeValue) || pe.test(t.nodeValue)) : e.filter((t) => t instanceof RegExp).some((t) => t.test(i));
        },
        be = {
            "*": ["class", "dir", "id", "lang", "role", /^aria-[\w-]*$/i],
            a: ["target", "href", "title", "rel"],
            area: [],
            b: [],
            br: [],
            col: [],
            code: [],
            div: [],
            em: [],
            hr: [],
            h1: [],
            h2: [],
            h3: [],
            h4: [],
            h5: [],
            h6: [],
            i: [],
            img: ["src", "srcset", "alt", "title", "width", "height"],
            li: [],
            ol: [],
            p: [],
            pre: [],
            s: [],
            small: [],
            span: [],
            sub: [],
            sup: [],
            strong: [],
            u: [],
            ul: [],
        },
        ve = { allowList: be, content: {}, extraClass: "", html: !1, sanitize: !0, sanitizeFn: null, template: "<div></div>" },
        ye = { allowList: "object", content: "object", extraClass: "(string|function)", html: "boolean", sanitize: "boolean", sanitizeFn: "(null|function)", template: "string" },
        we = { entry: "(string|element|function|null)", selector: "(string|element)" };
    class Ae extends R {
        constructor(t) {
            super(), (this._config = this._getConfig(t));
        }
        static get Default() {
            return ve;
        }
        static get DefaultType() {
            return ye;
        }
        static get NAME() {
            return "TemplateFactory";
        }
        getContent() {
            return Object.values(this._config.content)
                .map((t) => this._resolvePossibleFunction(t))
                .filter(Boolean);
        }
        hasContent() {
            return this.getContent().length > 0;
        }
        changeContent(t) {
            return this._checkContent(t), (this._config.content = { ...this._config.content, ...t }), this;
        }
        toHtml() {
            const t = document.createElement("div");
            t.innerHTML = this._maybeSanitize(this._config.template);
            for (const [e, i] of Object.entries(this._config.content)) this._setContent(t, i, e);
            const e = t.children[0],
                i = this._resolvePossibleFunction(this._config.extraClass);
            return i && e.classList.add(...i.split(" ")), e;
        }
        _typeCheckConfig(t) {
            super._typeCheckConfig(t), this._checkContent(t.content);
        }
        _checkContent(t) {
            for (const [e, i] of Object.entries(t)) super._typeCheckConfig({ selector: e, entry: i }, we);
        }
        _setContent(t, e, i) {
            const s = U.findOne(i, t);
            s && ((e = this._resolvePossibleFunction(e)) ? (l(e) ? this._putElementInTemplate(c(e), s) : this._config.html ? (s.innerHTML = this._maybeSanitize(e)) : (s.textContent = e)) : s.remove());
        }
        _maybeSanitize(t) {
            return this._config.sanitize
                ? (function (t, e, i) {
                      if (!t.length) return t;
                      if (i && "function" == typeof i) return i(t);
                      const s = new window.DOMParser().parseFromString(t, "text/html"),
                          n = [].concat(...s.body.querySelectorAll("*"));
                      for (const t of n) {
                          const i = t.nodeName.toLowerCase();
                          if (!Object.keys(e).includes(i)) {
                              t.remove();
                              continue;
                          }
                          const s = [].concat(...t.attributes),
                              n = [].concat(e["*"] || [], e[i] || []);
                          for (const e of s) me(e, n) || t.removeAttribute(e.nodeName);
                      }
                      return s.body.innerHTML;
                  })(t, this._config.allowList, this._config.sanitizeFn)
                : t;
        }
        _resolvePossibleFunction(t) {
            return "function" == typeof t ? t(this) : t;
        }
        _putElementInTemplate(t, e) {
            if (this._config.html) return (e.innerHTML = ""), void e.append(t);
            e.textContent = t.textContent;
        }
    }
    const Ee = new Set(["sanitize", "allowList", "sanitizeFn"]),
        Ce = "fade",
        Te = "show",
        ke = ".modal",
        Le = "hide.bs.modal",
        Oe = "hover",
        Ie = "focus",
        Se = { AUTO: "auto", TOP: "top", RIGHT: m() ? "left" : "right", BOTTOM: "bottom", LEFT: m() ? "right" : "left" },
        De = {
            allowList: be,
            animation: !0,
            boundary: "clippingParents",
            container: !1,
            customClass: "",
            delay: 0,
            fallbackPlacements: ["top", "right", "bottom", "left"],
            html: !1,
            offset: [0, 0],
            placement: "top",
            popperConfig: null,
            sanitize: !0,
            sanitizeFn: null,
            selector: !1,
            template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>',
            title: "",
            trigger: "hover focus",
        },
        Ne = {
            allowList: "object",
            animation: "boolean",
            boundary: "(string|element)",
            container: "(string|element|boolean)",
            customClass: "(string|function)",
            delay: "(number|object)",
            fallbackPlacements: "array",
            html: "boolean",
            offset: "(array|string|function)",
            placement: "(string|function)",
            popperConfig: "(null|object|function)",
            sanitize: "boolean",
            sanitizeFn: "(null|function)",
            selector: "(string|boolean)",
            template: "string",
            title: "(string|element|function)",
            trigger: "string",
        };
    class Pe extends V {
        constructor(t, e) {
            if (void 0 === i) throw new TypeError("Bootstrap's tooltips require Popper (https://popper.js.org)");
            super(t, e),
                (this._isEnabled = !0),
                (this._timeout = 0),
                (this._isHovered = null),
                (this._activeTrigger = {}),
                (this._popper = null),
                (this._templateFactory = null),
                (this._newContent = null),
                (this.tip = null),
                this._setListeners(),
                this._config.selector || this._fixTitle();
        }
        static get Default() {
            return De;
        }
        static get DefaultType() {
            return Ne;
        }
        static get NAME() {
            return "tooltip";
        }
        enable() {
            this._isEnabled = !0;
        }
        disable() {
            this._isEnabled = !1;
        }
        toggleEnabled() {
            this._isEnabled = !this._isEnabled;
        }
        toggle() {
            this._isEnabled && ((this._activeTrigger.click = !this._activeTrigger.click), this._isShown() ? this._leave() : this._enter());
        }
        dispose() {
            clearTimeout(this._timeout),
                $.off(this._element.closest(ke), Le, this._hideModalHandler),
                this._element.getAttribute("data-bs-original-title") && this._element.setAttribute("title", this._element.getAttribute("data-bs-original-title")),
                this._disposePopper(),
                super.dispose();
        }
        show() {
            if ("none" === this._element.style.display) throw new Error("Please use show on visible elements");
            if (!this._isWithContent() || !this._isEnabled) return;
            const t = $.trigger(this._element, this.constructor.eventName("show")),
                e = (u(this._element) || this._element.ownerDocument.documentElement).contains(this._element);
            if (t.defaultPrevented || !e) return;
            this._disposePopper();
            const i = this._getTipElement();
            this._element.setAttribute("aria-describedby", i.getAttribute("id"));
            const { container: s } = this._config;
            if (
                (this._element.ownerDocument.documentElement.contains(this.tip) || (s.append(i), $.trigger(this._element, this.constructor.eventName("inserted"))),
                (this._popper = this._createPopper(i)),
                i.classList.add(Te),
                "ontouchstart" in document.documentElement)
            )
                for (const t of [].concat(...document.body.children)) $.on(t, "mouseover", _);
            this._queueCallback(
                () => {
                    $.trigger(this._element, this.constructor.eventName("shown")), !1 === this._isHovered && this._leave(), (this._isHovered = !1);
                },
                this.tip,
                this._isAnimated()
            );
        }
        hide() {
            if (this._isShown() && !$.trigger(this._element, this.constructor.eventName("hide")).defaultPrevented) {
                if ((this._getTipElement().classList.remove(Te), "ontouchstart" in document.documentElement)) for (const t of [].concat(...document.body.children)) $.off(t, "mouseover", _);
                (this._activeTrigger.click = !1),
                    (this._activeTrigger.focus = !1),
                    (this._activeTrigger.hover = !1),
                    (this._isHovered = null),
                    this._queueCallback(
                        () => {
                            this._isWithActiveTrigger() || (this._isHovered || this._disposePopper(), this._element.removeAttribute("aria-describedby"), $.trigger(this._element, this.constructor.eventName("hidden")));
                        },
                        this.tip,
                        this._isAnimated()
                    );
            }
        }
        update() {
            this._popper && this._popper.update();
        }
        _isWithContent() {
            return Boolean(this._getTitle());
        }
        _getTipElement() {
            return this.tip || (this.tip = this._createTipElement(this._newContent || this._getContentForTemplate())), this.tip;
        }
        _createTipElement(t) {
            const e = this._getTemplateFactory(t).toHtml();
            if (!e) return null;
            e.classList.remove(Ce, Te), e.classList.add(`bs-${this.constructor.NAME}-auto`);
            const i = ((t) => {
                do {
                    t += Math.floor(1e6 * Math.random());
                } while (document.getElementById(t));
                return t;
            })(this.constructor.NAME).toString();
            return e.setAttribute("id", i), this._isAnimated() && e.classList.add(Ce), e;
        }
        setContent(t) {
            (this._newContent = t), this._isShown() && (this._disposePopper(), this.show());
        }
        _getTemplateFactory(t) {
            return (
                this._templateFactory ? this._templateFactory.changeContent(t) : (this._templateFactory = new Ae({ ...this._config, content: t, extraClass: this._resolvePossibleFunction(this._config.customClass) })), this._templateFactory
            );
        }
        _getContentForTemplate() {
            return { ".tooltip-inner": this._getTitle() };
        }
        _getTitle() {
            return this._resolvePossibleFunction(this._config.title) || this._element.getAttribute("data-bs-original-title");
        }
        _initializeOnDelegatedTarget(t) {
            return this.constructor.getOrCreateInstance(t.delegateTarget, this._getDelegateConfig());
        }
        _isAnimated() {
            return this._config.animation || (this.tip && this.tip.classList.contains(Ce));
        }
        _isShown() {
            return this.tip && this.tip.classList.contains(Te);
        }
        _createPopper(t) {
            const e = "function" == typeof this._config.placement ? this._config.placement.call(this, t, this._element) : this._config.placement,
                s = Se[e.toUpperCase()];
            return i.createPopper(this._element, t, this._getPopperConfig(s));
        }
        _getOffset() {
            const { offset: t } = this._config;
            return "string" == typeof t ? t.split(",").map((t) => Number.parseInt(t, 10)) : "function" == typeof t ? (e) => t(e, this._element) : t;
        }
        _resolvePossibleFunction(t) {
            return "function" == typeof t ? t.call(this._element) : t;
        }
        _getPopperConfig(t) {
            const e = {
                placement: t,
                modifiers: [
                    { name: "flip", options: { fallbackPlacements: this._config.fallbackPlacements } },
                    { name: "offset", options: { offset: this._getOffset() } },
                    { name: "preventOverflow", options: { boundary: this._config.boundary } },
                    { name: "arrow", options: { element: `.${this.constructor.NAME}-arrow` } },
                    {
                        name: "preSetPlacement",
                        enabled: !0,
                        phase: "beforeMain",
                        fn: (t) => {
                            this._getTipElement().setAttribute("data-popper-placement", t.state.placement);
                        },
                    },
                ],
            };
            return { ...e, ...("function" == typeof this._config.popperConfig ? this._config.popperConfig(e) : this._config.popperConfig) };
        }
        _setListeners() {
            const t = this._config.trigger.split(" ");
            for (const e of t)
                if ("click" === e)
                    $.on(this._element, this.constructor.eventName("click"), this._config.selector, (t) => {
                        this._initializeOnDelegatedTarget(t).toggle();
                    });
                else if ("manual" !== e) {
                    const t = e === Oe ? this.constructor.eventName("mouseenter") : this.constructor.eventName("focusin"),
                        i = e === Oe ? this.constructor.eventName("mouseleave") : this.constructor.eventName("focusout");
                    $.on(this._element, t, this._config.selector, (t) => {
                        const e = this._initializeOnDelegatedTarget(t);
                        (e._activeTrigger["focusin" === t.type ? Ie : Oe] = !0), e._enter();
                    }),
                        $.on(this._element, i, this._config.selector, (t) => {
                            const e = this._initializeOnDelegatedTarget(t);
                            (e._activeTrigger["focusout" === t.type ? Ie : Oe] = e._element.contains(t.relatedTarget)), e._leave();
                        });
                }
            (this._hideModalHandler = () => {
                this._element && this.hide();
            }),
                $.on(this._element.closest(ke), Le, this._hideModalHandler);
        }
        _fixTitle() {
            const t = this._element.getAttribute("title");
            t && (this._element.getAttribute("aria-label") || this._element.textContent.trim() || this._element.setAttribute("aria-label", t), this._element.setAttribute("data-bs-original-title", t), this._element.removeAttribute("title"));
        }
        _enter() {
            this._isShown() || this._isHovered
                ? (this._isHovered = !0)
                : ((this._isHovered = !0),
                  this._setTimeout(() => {
                      this._isHovered && this.show();
                  }, this._config.delay.show));
        }
        _leave() {
            this._isWithActiveTrigger() ||
                ((this._isHovered = !1),
                this._setTimeout(() => {
                    this._isHovered || this.hide();
                }, this._config.delay.hide));
        }
        _setTimeout(t, e) {
            clearTimeout(this._timeout), (this._timeout = setTimeout(t, e));
        }
        _isWithActiveTrigger() {
            return Object.values(this._activeTrigger).includes(!0);
        }
        _getConfig(t) {
            const e = W.getDataAttributes(this._element);
            for (const t of Object.keys(e)) Ee.has(t) && delete e[t];
            return (t = { ...e, ...("object" == typeof t && t ? t : {}) }), (t = this._mergeConfigObj(t)), (t = this._configAfterMerge(t)), this._typeCheckConfig(t), t;
        }
        _configAfterMerge(t) {
            return (
                (t.container = !1 === t.container ? document.body : c(t.container)),
                "number" == typeof t.delay && (t.delay = { show: t.delay, hide: t.delay }),
                "number" == typeof t.title && (t.title = t.title.toString()),
                "number" == typeof t.content && (t.content = t.content.toString()),
                t
            );
        }
        _getDelegateConfig() {
            const t = {};
            for (const e in this._config) this.constructor.Default[e] !== this._config[e] && (t[e] = this._config[e]);
            return (t.selector = !1), (t.trigger = "manual"), t;
        }
        _disposePopper() {
            this._popper && (this._popper.destroy(), (this._popper = null)), this.tip && (this.tip.remove(), (this.tip = null));
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = Pe.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === e[t]) throw new TypeError(`No method named "${t}"`);
                    e[t]();
                }
            });
        }
    }
    b(Pe);
    const xe = {
            ...Pe.Default,
            content: "",
            offset: [0, 8],
            placement: "right",
            template: '<div class="popover" role="tooltip"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>',
            trigger: "click",
        },
        Me = { ...Pe.DefaultType, content: "(null|string|element|function)" };
    class je extends Pe {
        static get Default() {
            return xe;
        }
        static get DefaultType() {
            return Me;
        }
        static get NAME() {
            return "popover";
        }
        _isWithContent() {
            return this._getTitle() || this._getContent();
        }
        _getContentForTemplate() {
            return { ".popover-header": this._getTitle(), ".popover-body": this._getContent() };
        }
        _getContent() {
            return this._resolvePossibleFunction(this._config.content);
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = je.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === e[t]) throw new TypeError(`No method named "${t}"`);
                    e[t]();
                }
            });
        }
    }
    b(je);
    const $e = "click.bs.scrollspy",
        Fe = "active",
        ze = "[href]",
        He = { offset: null, rootMargin: "0px 0px -25%", smoothScroll: !1, target: null, threshold: [0.1, 0.5, 1] },
        qe = { offset: "(number|null)", rootMargin: "string", smoothScroll: "boolean", target: "element", threshold: "array" };
    class Be extends V {
        constructor(t, e) {
            super(t, e),
                (this._targetLinks = new Map()),
                (this._observableSections = new Map()),
                (this._rootElement = "visible" === getComputedStyle(this._element).overflowY ? null : this._element),
                (this._activeTarget = null),
                (this._observer = null),
                (this._previousScrollData = { visibleEntryTop: 0, parentScrollTop: 0 }),
                this.refresh();
        }
        static get Default() {
            return He;
        }
        static get DefaultType() {
            return qe;
        }
        static get NAME() {
            return "scrollspy";
        }
        refresh() {
            this._initializeTargetsAndObservables(), this._maybeEnableSmoothScroll(), this._observer ? this._observer.disconnect() : (this._observer = this._getNewObserver());
            for (const t of this._observableSections.values()) this._observer.observe(t);
        }
        dispose() {
            this._observer.disconnect(), super.dispose();
        }
        _configAfterMerge(t) {
            return (t.target = c(t.target) || document.body), (t.rootMargin = t.offset ? `${t.offset}px 0px -30%` : t.rootMargin), "string" == typeof t.threshold && (t.threshold = t.threshold.split(",").map((t) => Number.parseFloat(t))), t;
        }
        _maybeEnableSmoothScroll() {
            this._config.smoothScroll &&
                ($.off(this._config.target, $e),
                $.on(this._config.target, $e, ze, (t) => {
                    const e = this._observableSections.get(t.target.hash);
                    if (e) {
                        t.preventDefault();
                        const i = this._rootElement || window,
                            s = e.offsetTop - this._element.offsetTop;
                        if (i.scrollTo) return void i.scrollTo({ top: s, behavior: "smooth" });
                        i.scrollTop = s;
                    }
                }));
        }
        _getNewObserver() {
            const t = { root: this._rootElement, threshold: this._config.threshold, rootMargin: this._config.rootMargin };
            return new IntersectionObserver((t) => this._observerCallback(t), t);
        }
        _observerCallback(t) {
            const e = (t) => this._targetLinks.get(`#${t.target.id}`),
                i = (t) => {
                    (this._previousScrollData.visibleEntryTop = t.target.offsetTop), this._process(e(t));
                },
                s = (this._rootElement || document.documentElement).scrollTop,
                n = s >= this._previousScrollData.parentScrollTop;
            this._previousScrollData.parentScrollTop = s;
            for (const o of t) {
                if (!o.isIntersecting) {
                    (this._activeTarget = null), this._clearActiveClass(e(o));
                    continue;
                }
                const t = o.target.offsetTop >= this._previousScrollData.visibleEntryTop;
                if (n && t) {
                    if ((i(o), !s)) return;
                } else n || t || i(o);
            }
        }
        _initializeTargetsAndObservables() {
            (this._targetLinks = new Map()), (this._observableSections = new Map());
            const t = U.find(ze, this._config.target);
            for (const e of t) {
                if (!e.hash || d(e)) continue;
                const t = U.findOne(e.hash, this._element);
                h(t) && (this._targetLinks.set(e.hash, e), this._observableSections.set(e.hash, t));
            }
        }
        _process(t) {
            this._activeTarget !== t && (this._clearActiveClass(this._config.target), (this._activeTarget = t), t.classList.add(Fe), this._activateParents(t), $.trigger(this._element, "activate.bs.scrollspy", { relatedTarget: t }));
        }
        _activateParents(t) {
            if (t.classList.contains("dropdown-item")) U.findOne(".dropdown-toggle", t.closest(".dropdown")).classList.add(Fe);
            else for (const e of U.parents(t, ".nav, .list-group")) for (const t of U.prev(e, ".nav-link, .nav-item > .nav-link, .list-group-item")) t.classList.add(Fe);
        }
        _clearActiveClass(t) {
            t.classList.remove(Fe);
            const e = U.find("[href].active", t);
            for (const t of e) t.classList.remove(Fe);
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = Be.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === e[t] || t.startsWith("_") || "constructor" === t) throw new TypeError(`No method named "${t}"`);
                    e[t]();
                }
            });
        }
    }
    $.on(window, "load.bs.scrollspy.data-api", () => {
        for (const t of U.find('[data-bs-spy="scroll"]')) Be.getOrCreateInstance(t);
    }),
        b(Be);
    const We = "ArrowLeft",
        Re = "ArrowRight",
        Ve = "ArrowUp",
        Ke = "ArrowDown",
        Qe = "active",
        Xe = "fade",
        Ye = "show",
        Ue = '[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]',
        Ge = `.nav-link:not(.dropdown-toggle), .list-group-item:not(.dropdown-toggle), [role="tab"]:not(.dropdown-toggle), ${Ue}`;
    class Je extends V {
        constructor(t) {
            super(t),
                (this._parent = this._element.closest('.list-group, .nav, [role="tablist"]')),
                this._parent && (this._setInitialAttributes(this._parent, this._getChildren()), $.on(this._element, "keydown.bs.tab", (t) => this._keydown(t)));
        }
        static get NAME() {
            return "tab";
        }
        show() {
            const t = this._element;
            if (this._elemIsActive(t)) return;
            const e = this._getActiveElem(),
                i = e ? $.trigger(e, "hide.bs.tab", { relatedTarget: t }) : null;
            $.trigger(t, "show.bs.tab", { relatedTarget: e }).defaultPrevented || (i && i.defaultPrevented) || (this._deactivate(e, t), this._activate(t, e));
        }
        _activate(t, e) {
            t &&
                (t.classList.add(Qe),
                this._activate(r(t)),
                this._queueCallback(
                    () => {
                        "tab" === t.getAttribute("role") ? (t.removeAttribute("tabindex"), t.setAttribute("aria-selected", !0), this._toggleDropDown(t, !0), $.trigger(t, "shown.bs.tab", { relatedTarget: e })) : t.classList.add(Ye);
                    },
                    t,
                    t.classList.contains(Xe)
                ));
        }
        _deactivate(t, e) {
            t &&
                (t.classList.remove(Qe),
                t.blur(),
                this._deactivate(r(t)),
                this._queueCallback(
                    () => {
                        "tab" === t.getAttribute("role") ? (t.setAttribute("aria-selected", !1), t.setAttribute("tabindex", "-1"), this._toggleDropDown(t, !1), $.trigger(t, "hidden.bs.tab", { relatedTarget: e })) : t.classList.remove(Ye);
                    },
                    t,
                    t.classList.contains(Xe)
                ));
        }
        _keydown(t) {
            if (![We, Re, Ve, Ke].includes(t.key)) return;
            t.stopPropagation(), t.preventDefault();
            const e = [Re, Ke].includes(t.key),
                i = w(
                    this._getChildren().filter((t) => !d(t)),
                    t.target,
                    e,
                    !0
                );
            i && (i.focus({ preventScroll: !0 }), Je.getOrCreateInstance(i).show());
        }
        _getChildren() {
            return U.find(Ge, this._parent);
        }
        _getActiveElem() {
            return this._getChildren().find((t) => this._elemIsActive(t)) || null;
        }
        _setInitialAttributes(t, e) {
            this._setAttributeIfNotExists(t, "role", "tablist");
            for (const t of e) this._setInitialAttributesOnChild(t);
        }
        _setInitialAttributesOnChild(t) {
            t = this._getInnerElement(t);
            const e = this._elemIsActive(t),
                i = this._getOuterElement(t);
            t.setAttribute("aria-selected", e),
                i !== t && this._setAttributeIfNotExists(i, "role", "presentation"),
                e || t.setAttribute("tabindex", "-1"),
                this._setAttributeIfNotExists(t, "role", "tab"),
                this._setInitialAttributesOnTargetPanel(t);
        }
        _setInitialAttributesOnTargetPanel(t) {
            const e = r(t);
            e && (this._setAttributeIfNotExists(e, "role", "tabpanel"), t.id && this._setAttributeIfNotExists(e, "aria-labelledby", `#${t.id}`));
        }
        _toggleDropDown(t, e) {
            const i = this._getOuterElement(t);
            if (!i.classList.contains("dropdown")) return;
            const s = (t, s) => {
                const n = U.findOne(t, i);
                n && n.classList.toggle(s, e);
            };
            s(".dropdown-toggle", Qe), s(".dropdown-menu", Ye), i.setAttribute("aria-expanded", e);
        }
        _setAttributeIfNotExists(t, e, i) {
            t.hasAttribute(e) || t.setAttribute(e, i);
        }
        _elemIsActive(t) {
            return t.classList.contains(Qe);
        }
        _getInnerElement(t) {
            return t.matches(Ge) ? t : U.findOne(Ge, t);
        }
        _getOuterElement(t) {
            return t.closest(".nav-item, .list-group-item") || t;
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = Je.getOrCreateInstance(this);
                if ("string" == typeof t) {
                    if (void 0 === e[t] || t.startsWith("_") || "constructor" === t) throw new TypeError(`No method named "${t}"`);
                    e[t]();
                }
            });
        }
    }
    $.on(document, "click.bs.tab", Ue, function (t) {
        ["A", "AREA"].includes(this.tagName) && t.preventDefault(), d(this) || Je.getOrCreateInstance(this).show();
    }),
        $.on(window, "load.bs.tab", () => {
            for (const t of U.find('.active[data-bs-toggle="tab"], .active[data-bs-toggle="pill"], .active[data-bs-toggle="list"]')) Je.getOrCreateInstance(t);
        }),
        b(Je);
    const Ze = "hide",
        ti = "show",
        ei = "showing",
        ii = { animation: "boolean", autohide: "boolean", delay: "number" },
        si = { animation: !0, autohide: !0, delay: 5e3 };
    class ni extends V {
        constructor(t, e) {
            super(t, e), (this._timeout = null), (this._hasMouseInteraction = !1), (this._hasKeyboardInteraction = !1), this._setListeners();
        }
        static get Default() {
            return si;
        }
        static get DefaultType() {
            return ii;
        }
        static get NAME() {
            return "toast";
        }
        show() {
            $.trigger(this._element, "show.bs.toast").defaultPrevented ||
                (this._clearTimeout(),
                this._config.animation && this._element.classList.add("fade"),
                this._element.classList.remove(Ze),
                g(this._element),
                this._element.classList.add(ti, ei),
                this._queueCallback(
                    () => {
                        this._element.classList.remove(ei), $.trigger(this._element, "shown.bs.toast"), this._maybeScheduleHide();
                    },
                    this._element,
                    this._config.animation
                ));
        }
        hide() {
            this.isShown() &&
                ($.trigger(this._element, "hide.bs.toast").defaultPrevented ||
                    (this._element.classList.add(ei),
                    this._queueCallback(
                        () => {
                            this._element.classList.add(Ze), this._element.classList.remove(ei, ti), $.trigger(this._element, "hidden.bs.toast");
                        },
                        this._element,
                        this._config.animation
                    )));
        }
        dispose() {
            this._clearTimeout(), this.isShown() && this._element.classList.remove(ti), super.dispose();
        }
        isShown() {
            return this._element.classList.contains(ti);
        }
        _maybeScheduleHide() {
            this._config.autohide &&
                (this._hasMouseInteraction ||
                    this._hasKeyboardInteraction ||
                    (this._timeout = setTimeout(() => {
                        this.hide();
                    }, this._config.delay)));
        }
        _onInteraction(t, e) {
            switch (t.type) {
                case "mouseover":
                case "mouseout":
                    this._hasMouseInteraction = e;
                    break;
                case "focusin":
                case "focusout":
                    this._hasKeyboardInteraction = e;
            }
            if (e) return void this._clearTimeout();
            const i = t.relatedTarget;
            this._element === i || this._element.contains(i) || this._maybeScheduleHide();
        }
        _setListeners() {
            $.on(this._element, "mouseover.bs.toast", (t) => this._onInteraction(t, !0)),
                $.on(this._element, "mouseout.bs.toast", (t) => this._onInteraction(t, !1)),
                $.on(this._element, "focusin.bs.toast", (t) => this._onInteraction(t, !0)),
                $.on(this._element, "focusout.bs.toast", (t) => this._onInteraction(t, !1));
        }
        _clearTimeout() {
            clearTimeout(this._timeout), (this._timeout = null);
        }
        static jQueryInterface(t) {
            return this.each(function () {
                const e = ni.getOrCreateInstance(this, t);
                if ("string" == typeof t) {
                    if (void 0 === e[t]) throw new TypeError(`No method named "${t}"`);
                    e[t](this);
                }
            });
        }
    }
    return K(ni), b(ni), { Alert: Q, Button: Y, Carousel: ht, Collapse: mt, Dropdown: Mt, Modal: ne, Offcanvas: _e, Popover: je, ScrollSpy: Be, Tab: Je, Toast: ni, Tooltip: Pe };
});
/*!
 * Masonry PACKAGED v4.2.2
 * Cascading grid layout library
 * https://masonry.desandro.com
 * MIT License
 * by David DeSandro
 */

!(function (t, e) {
    "function" == typeof define && define.amd
        ? define("jquery-bridget/jquery-bridget", ["jquery"], function (i) {
              return e(t, i);
          })
        : "object" == typeof module && module.exports
        ? (module.exports = e(t, require("jquery")))
        : (t.jQueryBridget = e(t, t.jQuery));
})(window, function (t, e) {
    "use strict";
    function i(i, r, a) {
        function h(t, e, n) {
            var o,
                r = "$()." + i + '("' + e + '")';
            return (
                t.each(function (t, h) {
                    var u = a.data(h, i);
                    if (!u) return void s(i + " not initialized. Cannot call methods, i.e. " + r);
                    var d = u[e];
                    if (!d || "_" == e.charAt(0)) return void s(r + " is not a valid method");
                    var l = d.apply(u, n);
                    o = void 0 === o ? l : o;
                }),
                void 0 !== o ? o : t
            );
        }
        function u(t, e) {
            t.each(function (t, n) {
                var o = a.data(n, i);
                o ? (o.option(e), o._init()) : ((o = new r(n, e)), a.data(n, i, o));
            });
        }
        (a = a || e || t.jQuery),
            a &&
                (r.prototype.option ||
                    (r.prototype.option = function (t) {
                        a.isPlainObject(t) && (this.options = a.extend(!0, this.options, t));
                    }),
                (a.fn[i] = function (t) {
                    if ("string" == typeof t) {
                        var e = o.call(arguments, 1);
                        return h(this, t, e);
                    }
                    return u(this, t), this;
                }),
                n(a));
    }
    function n(t) {
        !t || (t && t.bridget) || (t.bridget = i);
    }
    var o = Array.prototype.slice,
        r = t.console,
        s =
            "undefined" == typeof r
                ? function () {}
                : function (t) {
                      r.error(t);
                  };
    return n(e || t.jQuery), i;
}),
    (function (t, e) {
        "function" == typeof define && define.amd ? define("ev-emitter/ev-emitter", e) : "object" == typeof module && module.exports ? (module.exports = e()) : (t.EvEmitter = e());
    })("undefined" != typeof window ? window : this, function () {
        function t() {}
        var e = t.prototype;
        return (
            (e.on = function (t, e) {
                if (t && e) {
                    var i = (this._events = this._events || {}),
                        n = (i[t] = i[t] || []);
                    return -1 == n.indexOf(e) && n.push(e), this;
                }
            }),
            (e.once = function (t, e) {
                if (t && e) {
                    this.on(t, e);
                    var i = (this._onceEvents = this._onceEvents || {}),
                        n = (i[t] = i[t] || {});
                    return (n[e] = !0), this;
                }
            }),
            (e.off = function (t, e) {
                var i = this._events && this._events[t];
                if (i && i.length) {
                    var n = i.indexOf(e);
                    return -1 != n && i.splice(n, 1), this;
                }
            }),
            (e.emitEvent = function (t, e) {
                var i = this._events && this._events[t];
                if (i && i.length) {
                    (i = i.slice(0)), (e = e || []);
                    for (var n = this._onceEvents && this._onceEvents[t], o = 0; o < i.length; o++) {
                        var r = i[o],
                            s = n && n[r];
                        s && (this.off(t, r), delete n[r]), r.apply(this, e);
                    }
                    return this;
                }
            }),
            (e.allOff = function () {
                delete this._events, delete this._onceEvents;
            }),
            t
        );
    }),
    (function (t, e) {
        "function" == typeof define && define.amd ? define("get-size/get-size", e) : "object" == typeof module && module.exports ? (module.exports = e()) : (t.getSize = e());
    })(window, function () {
        "use strict";
        function t(t) {
            var e = parseFloat(t),
                i = -1 == t.indexOf("%") && !isNaN(e);
            return i && e;
        }
        function e() {}
        function i() {
            for (var t = { width: 0, height: 0, innerWidth: 0, innerHeight: 0, outerWidth: 0, outerHeight: 0 }, e = 0; u > e; e++) {
                var i = h[e];
                t[i] = 0;
            }
            return t;
        }
        function n(t) {
            var e = getComputedStyle(t);
            return e || a("Style returned " + e + ". Are you running this code in a hidden iframe on Firefox? See https://bit.ly/getsizebug1"), e;
        }
        function o() {
            if (!d) {
                d = !0;
                var e = document.createElement("div");
                (e.style.width = "200px"), (e.style.padding = "1px 2px 3px 4px"), (e.style.borderStyle = "solid"), (e.style.borderWidth = "1px 2px 3px 4px"), (e.style.boxSizing = "border-box");
                var i = document.body || document.documentElement;
                i.appendChild(e);
                var o = n(e);
                (s = 200 == Math.round(t(o.width))), (r.isBoxSizeOuter = s), i.removeChild(e);
            }
        }
        function r(e) {
            if ((o(), "string" == typeof e && (e = document.querySelector(e)), e && "object" == typeof e && e.nodeType)) {
                var r = n(e);
                if ("none" == r.display) return i();
                var a = {};
                (a.width = e.offsetWidth), (a.height = e.offsetHeight);
                for (var d = (a.isBorderBox = "border-box" == r.boxSizing), l = 0; u > l; l++) {
                    var c = h[l],
                        f = r[c],
                        m = parseFloat(f);
                    a[c] = isNaN(m) ? 0 : m;
                }
                var p = a.paddingLeft + a.paddingRight,
                    g = a.paddingTop + a.paddingBottom,
                    y = a.marginLeft + a.marginRight,
                    v = a.marginTop + a.marginBottom,
                    _ = a.borderLeftWidth + a.borderRightWidth,
                    z = a.borderTopWidth + a.borderBottomWidth,
                    E = d && s,
                    b = t(r.width);
                b !== !1 && (a.width = b + (E ? 0 : p + _));
                var x = t(r.height);
                return x !== !1 && (a.height = x + (E ? 0 : g + z)), (a.innerWidth = a.width - (p + _)), (a.innerHeight = a.height - (g + z)), (a.outerWidth = a.width + y), (a.outerHeight = a.height + v), a;
            }
        }
        var s,
            a =
                "undefined" == typeof console
                    ? e
                    : function (t) {
                          console.error(t);
                      },
            h = ["paddingLeft", "paddingRight", "paddingTop", "paddingBottom", "marginLeft", "marginRight", "marginTop", "marginBottom", "borderLeftWidth", "borderRightWidth", "borderTopWidth", "borderBottomWidth"],
            u = h.length,
            d = !1;
        return r;
    }),
    (function (t, e) {
        "use strict";
        "function" == typeof define && define.amd ? define("desandro-matches-selector/matches-selector", e) : "object" == typeof module && module.exports ? (module.exports = e()) : (t.matchesSelector = e());
    })(window, function () {
        "use strict";
        var t = (function () {
            var t = window.Element.prototype;
            if (t.matches) return "matches";
            if (t.matchesSelector) return "matchesSelector";
            for (var e = ["webkit", "moz", "ms", "o"], i = 0; i < e.length; i++) {
                var n = e[i],
                    o = n + "MatchesSelector";
                if (t[o]) return o;
            }
        })();
        return function (e, i) {
            return e[t](i);
        };
    }),
    (function (t, e) {
        "function" == typeof define && define.amd
            ? define("fizzy-ui-utils/utils", ["desandro-matches-selector/matches-selector"], function (i) {
                  return e(t, i);
              })
            : "object" == typeof module && module.exports
            ? (module.exports = e(t, require("desandro-matches-selector")))
            : (t.fizzyUIUtils = e(t, t.matchesSelector));
    })(window, function (t, e) {
        var i = {};
        (i.extend = function (t, e) {
            for (var i in e) t[i] = e[i];
            return t;
        }),
            (i.modulo = function (t, e) {
                return ((t % e) + e) % e;
            });
        var n = Array.prototype.slice;
        (i.makeArray = function (t) {
            if (Array.isArray(t)) return t;
            if (null === t || void 0 === t) return [];
            var e = "object" == typeof t && "number" == typeof t.length;
            return e ? n.call(t) : [t];
        }),
            (i.removeFrom = function (t, e) {
                var i = t.indexOf(e);
                -1 != i && t.splice(i, 1);
            }),
            (i.getParent = function (t, i) {
                for (; t.parentNode && t != document.body; ) if (((t = t.parentNode), e(t, i))) return t;
            }),
            (i.getQueryElement = function (t) {
                return "string" == typeof t ? document.querySelector(t) : t;
            }),
            (i.handleEvent = function (t) {
                var e = "on" + t.type;
                this[e] && this[e](t);
            }),
            (i.filterFindElements = function (t, n) {
                t = i.makeArray(t);
                var o = [];
                return (
                    t.forEach(function (t) {
                        if (t instanceof HTMLElement) {
                            if (!n) return void o.push(t);
                            e(t, n) && o.push(t);
                            for (var i = t.querySelectorAll(n), r = 0; r < i.length; r++) o.push(i[r]);
                        }
                    }),
                    o
                );
            }),
            (i.debounceMethod = function (t, e, i) {
                i = i || 100;
                var n = t.prototype[e],
                    o = e + "Timeout";
                t.prototype[e] = function () {
                    var t = this[o];
                    clearTimeout(t);
                    var e = arguments,
                        r = this;
                    this[o] = setTimeout(function () {
                        n.apply(r, e), delete r[o];
                    }, i);
                };
            }),
            (i.docReady = function (t) {
                var e = document.readyState;
                "complete" == e || "interactive" == e ? setTimeout(t) : document.addEventListener("DOMContentLoaded", t);
            }),
            (i.toDashed = function (t) {
                return t
                    .replace(/(.)([A-Z])/g, function (t, e, i) {
                        return e + "-" + i;
                    })
                    .toLowerCase();
            });
        var o = t.console;
        return (
            (i.htmlInit = function (e, n) {
                i.docReady(function () {
                    var r = i.toDashed(n),
                        s = "data-" + r,
                        a = document.querySelectorAll("[" + s + "]"),
                        h = document.querySelectorAll(".js-" + r),
                        u = i.makeArray(a).concat(i.makeArray(h)),
                        d = s + "-options",
                        l = t.jQuery;
                    u.forEach(function (t) {
                        var i,
                            r = t.getAttribute(s) || t.getAttribute(d);
                        try {
                            i = r && JSON.parse(r);
                        } catch (a) {
                            return void (o && o.error("Error parsing " + s + " on " + t.className + ": " + a));
                        }
                        var h = new e(t, i);
                        l && l.data(t, n, h);
                    });
                });
            }),
            i
        );
    }),
    (function (t, e) {
        "function" == typeof define && define.amd
            ? define("outlayer/item", ["ev-emitter/ev-emitter", "get-size/get-size"], e)
            : "object" == typeof module && module.exports
            ? (module.exports = e(require("ev-emitter"), require("get-size")))
            : ((t.Outlayer = {}), (t.Outlayer.Item = e(t.EvEmitter, t.getSize)));
    })(window, function (t, e) {
        "use strict";
        function i(t) {
            for (var e in t) return !1;
            return (e = null), !0;
        }
        function n(t, e) {
            t && ((this.element = t), (this.layout = e), (this.position = { x: 0, y: 0 }), this._create());
        }
        function o(t) {
            return t.replace(/([A-Z])/g, function (t) {
                return "-" + t.toLowerCase();
            });
        }
        var r = document.documentElement.style,
            s = "string" == typeof r.transition ? "transition" : "WebkitTransition",
            a = "string" == typeof r.transform ? "transform" : "WebkitTransform",
            h = { WebkitTransition: "webkitTransitionEnd", transition: "transitionend" }[s],
            u = { transform: a, transition: s, transitionDuration: s + "Duration", transitionProperty: s + "Property", transitionDelay: s + "Delay" },
            d = (n.prototype = Object.create(t.prototype));
        (d.constructor = n),
            (d._create = function () {
                (this._transn = { ingProperties: {}, clean: {}, onEnd: {} }), this.css({ position: "absolute" });
            }),
            (d.handleEvent = function (t) {
                var e = "on" + t.type;
                this[e] && this[e](t);
            }),
            (d.getSize = function () {
                this.size = e(this.element);
            }),
            (d.css = function (t) {
                var e = this.element.style;
                for (var i in t) {
                    var n = u[i] || i;
                    e[n] = t[i];
                }
            }),
            (d.getPosition = function () {
                var t = getComputedStyle(this.element),
                    e = this.layout._getOption("originLeft"),
                    i = this.layout._getOption("originTop"),
                    n = t[e ? "left" : "right"],
                    o = t[i ? "top" : "bottom"],
                    r = parseFloat(n),
                    s = parseFloat(o),
                    a = this.layout.size;
                -1 != n.indexOf("%") && (r = (r / 100) * a.width),
                    -1 != o.indexOf("%") && (s = (s / 100) * a.height),
                    (r = isNaN(r) ? 0 : r),
                    (s = isNaN(s) ? 0 : s),
                    (r -= e ? a.paddingLeft : a.paddingRight),
                    (s -= i ? a.paddingTop : a.paddingBottom),
                    (this.position.x = r),
                    (this.position.y = s);
            }),
            (d.layoutPosition = function () {
                var t = this.layout.size,
                    e = {},
                    i = this.layout._getOption("originLeft"),
                    n = this.layout._getOption("originTop"),
                    o = i ? "paddingLeft" : "paddingRight",
                    r = i ? "left" : "right",
                    s = i ? "right" : "left",
                    a = this.position.x + t[o];
                (e[r] = this.getXValue(a)), (e[s] = "");
                var h = n ? "paddingTop" : "paddingBottom",
                    u = n ? "top" : "bottom",
                    d = n ? "bottom" : "top",
                    l = this.position.y + t[h];
                (e[u] = this.getYValue(l)), (e[d] = ""), this.css(e), this.emitEvent("layout", [this]);
            }),
            (d.getXValue = function (t) {
                var e = this.layout._getOption("horizontal");
                return this.layout.options.percentPosition && !e ? (t / this.layout.size.width) * 100 + "%" : t + "px";
            }),
            (d.getYValue = function (t) {
                var e = this.layout._getOption("horizontal");
                return this.layout.options.percentPosition && e ? (t / this.layout.size.height) * 100 + "%" : t + "px";
            }),
            (d._transitionTo = function (t, e) {
                this.getPosition();
                var i = this.position.x,
                    n = this.position.y,
                    o = t == this.position.x && e == this.position.y;
                if ((this.setPosition(t, e), o && !this.isTransitioning)) return void this.layoutPosition();
                var r = t - i,
                    s = e - n,
                    a = {};
                (a.transform = this.getTranslate(r, s)), this.transition({ to: a, onTransitionEnd: { transform: this.layoutPosition }, isCleaning: !0 });
            }),
            (d.getTranslate = function (t, e) {
                var i = this.layout._getOption("originLeft"),
                    n = this.layout._getOption("originTop");
                return (t = i ? t : -t), (e = n ? e : -e), "translate3d(" + t + "px, " + e + "px, 0)";
            }),
            (d.goTo = function (t, e) {
                this.setPosition(t, e), this.layoutPosition();
            }),
            (d.moveTo = d._transitionTo),
            (d.setPosition = function (t, e) {
                (this.position.x = parseFloat(t)), (this.position.y = parseFloat(e));
            }),
            (d._nonTransition = function (t) {
                this.css(t.to), t.isCleaning && this._removeStyles(t.to);
                for (var e in t.onTransitionEnd) t.onTransitionEnd[e].call(this);
            }),
            (d.transition = function (t) {
                if (!parseFloat(this.layout.options.transitionDuration)) return void this._nonTransition(t);
                var e = this._transn;
                for (var i in t.onTransitionEnd) e.onEnd[i] = t.onTransitionEnd[i];
                for (i in t.to) (e.ingProperties[i] = !0), t.isCleaning && (e.clean[i] = !0);
                if (t.from) {
                    this.css(t.from);
                    var n = this.element.offsetHeight;
                    n = null;
                }
                this.enableTransition(t.to), this.css(t.to), (this.isTransitioning = !0);
            });
        var l = "opacity," + o(a);
        (d.enableTransition = function () {
            if (!this.isTransitioning) {
                var t = this.layout.options.transitionDuration;
                (t = "number" == typeof t ? t + "ms" : t), this.css({ transitionProperty: l, transitionDuration: t, transitionDelay: this.staggerDelay || 0 }), this.element.addEventListener(h, this, !1);
            }
        }),
            (d.onwebkitTransitionEnd = function (t) {
                this.ontransitionend(t);
            }),
            (d.onotransitionend = function (t) {
                this.ontransitionend(t);
            });
        var c = { "-webkit-transform": "transform" };
        (d.ontransitionend = function (t) {
            if (t.target === this.element) {
                var e = this._transn,
                    n = c[t.propertyName] || t.propertyName;
                if ((delete e.ingProperties[n], i(e.ingProperties) && this.disableTransition(), n in e.clean && ((this.element.style[t.propertyName] = ""), delete e.clean[n]), n in e.onEnd)) {
                    var o = e.onEnd[n];
                    o.call(this), delete e.onEnd[n];
                }
                this.emitEvent("transitionEnd", [this]);
            }
        }),
            (d.disableTransition = function () {
                this.removeTransitionStyles(), this.element.removeEventListener(h, this, !1), (this.isTransitioning = !1);
            }),
            (d._removeStyles = function (t) {
                var e = {};
                for (var i in t) e[i] = "";
                this.css(e);
            });
        var f = { transitionProperty: "", transitionDuration: "", transitionDelay: "" };
        return (
            (d.removeTransitionStyles = function () {
                this.css(f);
            }),
            (d.stagger = function (t) {
                (t = isNaN(t) ? 0 : t), (this.staggerDelay = t + "ms");
            }),
            (d.removeElem = function () {
                this.element.parentNode.removeChild(this.element), this.css({ display: "" }), this.emitEvent("remove", [this]);
            }),
            (d.remove = function () {
                return s && parseFloat(this.layout.options.transitionDuration)
                    ? (this.once("transitionEnd", function () {
                          this.removeElem();
                      }),
                      void this.hide())
                    : void this.removeElem();
            }),
            (d.reveal = function () {
                delete this.isHidden, this.css({ display: "" });
                var t = this.layout.options,
                    e = {},
                    i = this.getHideRevealTransitionEndProperty("visibleStyle");
                (e[i] = this.onRevealTransitionEnd), this.transition({ from: t.hiddenStyle, to: t.visibleStyle, isCleaning: !0, onTransitionEnd: e });
            }),
            (d.onRevealTransitionEnd = function () {
                this.isHidden || this.emitEvent("reveal");
            }),
            (d.getHideRevealTransitionEndProperty = function (t) {
                var e = this.layout.options[t];
                if (e.opacity) return "opacity";
                for (var i in e) return i;
            }),
            (d.hide = function () {
                (this.isHidden = !0), this.css({ display: "" });
                var t = this.layout.options,
                    e = {},
                    i = this.getHideRevealTransitionEndProperty("hiddenStyle");
                (e[i] = this.onHideTransitionEnd), this.transition({ from: t.visibleStyle, to: t.hiddenStyle, isCleaning: !0, onTransitionEnd: e });
            }),
            (d.onHideTransitionEnd = function () {
                this.isHidden && (this.css({ display: "none" }), this.emitEvent("hide"));
            }),
            (d.destroy = function () {
                this.css({ position: "", left: "", right: "", top: "", bottom: "", transition: "", transform: "" });
            }),
            n
        );
    }),
    (function (t, e) {
        "use strict";
        "function" == typeof define && define.amd
            ? define("outlayer/outlayer", ["ev-emitter/ev-emitter", "get-size/get-size", "fizzy-ui-utils/utils", "./item"], function (i, n, o, r) {
                  return e(t, i, n, o, r);
              })
            : "object" == typeof module && module.exports
            ? (module.exports = e(t, require("ev-emitter"), require("get-size"), require("fizzy-ui-utils"), require("./item")))
            : (t.Outlayer = e(t, t.EvEmitter, t.getSize, t.fizzyUIUtils, t.Outlayer.Item));
    })(window, function (t, e, i, n, o) {
        "use strict";
        function r(t, e) {
            var i = n.getQueryElement(t);
            if (!i) return void (h && h.error("Bad element for " + this.constructor.namespace + ": " + (i || t)));
            (this.element = i), u && (this.$element = u(this.element)), (this.options = n.extend({}, this.constructor.defaults)), this.option(e);
            var o = ++l;
            (this.element.outlayerGUID = o), (c[o] = this), this._create();
            var r = this._getOption("initLayout");
            r && this.layout();
        }
        function s(t) {
            function e() {
                t.apply(this, arguments);
            }
            return (e.prototype = Object.create(t.prototype)), (e.prototype.constructor = e), e;
        }
        function a(t) {
            if ("number" == typeof t) return t;
            var e = t.match(/(^\d*\.?\d*)(\w*)/),
                i = e && e[1],
                n = e && e[2];
            if (!i.length) return 0;
            i = parseFloat(i);
            var o = m[n] || 1;
            return i * o;
        }
        var h = t.console,
            u = t.jQuery,
            d = function () {},
            l = 0,
            c = {};
        (r.namespace = "outlayer"),
            (r.Item = o),
            (r.defaults = {
                containerStyle: { position: "relative" },
                initLayout: !0,
                originLeft: !0,
                originTop: !0,
                resize: !0,
                resizeContainer: !0,
                transitionDuration: "0.4s",
                hiddenStyle: { opacity: 0, transform: "scale(0.001)" },
                visibleStyle: { opacity: 1, transform: "scale(1)" },
            });
        var f = r.prototype;
        n.extend(f, e.prototype),
            (f.option = function (t) {
                n.extend(this.options, t);
            }),
            (f._getOption = function (t) {
                var e = this.constructor.compatOptions[t];
                return e && void 0 !== this.options[e] ? this.options[e] : this.options[t];
            }),
            (r.compatOptions = {
                initLayout: "isInitLayout",
                horizontal: "isHorizontal",
                layoutInstant: "isLayoutInstant",
                originLeft: "isOriginLeft",
                originTop: "isOriginTop",
                resize: "isResizeBound",
                resizeContainer: "isResizingContainer",
            }),
            (f._create = function () {
                this.reloadItems(), (this.stamps = []), this.stamp(this.options.stamp), n.extend(this.element.style, this.options.containerStyle);
                var t = this._getOption("resize");
                t && this.bindResize();
            }),
            (f.reloadItems = function () {
                this.items = this._itemize(this.element.children);
            }),
            (f._itemize = function (t) {
                for (var e = this._filterFindItemElements(t), i = this.constructor.Item, n = [], o = 0; o < e.length; o++) {
                    var r = e[o],
                        s = new i(r, this);
                    n.push(s);
                }
                return n;
            }),
            (f._filterFindItemElements = function (t) {
                return n.filterFindElements(t, this.options.itemSelector);
            }),
            (f.getItemElements = function () {
                return this.items.map(function (t) {
                    return t.element;
                });
            }),
            (f.layout = function () {
                this._resetLayout(), this._manageStamps();
                var t = this._getOption("layoutInstant"),
                    e = void 0 !== t ? t : !this._isLayoutInited;
                this.layoutItems(this.items, e), (this._isLayoutInited = !0);
            }),
            (f._init = f.layout),
            (f._resetLayout = function () {
                this.getSize();
            }),
            (f.getSize = function () {
                this.size = i(this.element);
            }),
            (f._getMeasurement = function (t, e) {
                var n,
                    o = this.options[t];
                o ? ("string" == typeof o ? (n = this.element.querySelector(o)) : o instanceof HTMLElement && (n = o), (this[t] = n ? i(n)[e] : o)) : (this[t] = 0);
            }),
            (f.layoutItems = function (t, e) {
                (t = this._getItemsForLayout(t)), this._layoutItems(t, e), this._postLayout();
            }),
            (f._getItemsForLayout = function (t) {
                return t.filter(function (t) {
                    return !t.isIgnored;
                });
            }),
            (f._layoutItems = function (t, e) {
                if ((this._emitCompleteOnItems("layout", t), t && t.length)) {
                    var i = [];
                    t.forEach(function (t) {
                        var n = this._getItemLayoutPosition(t);
                        (n.item = t), (n.isInstant = e || t.isLayoutInstant), i.push(n);
                    }, this),
                        this._processLayoutQueue(i);
                }
            }),
            (f._getItemLayoutPosition = function () {
                return { x: 0, y: 0 };
            }),
            (f._processLayoutQueue = function (t) {
                this.updateStagger(),
                    t.forEach(function (t, e) {
                        this._positionItem(t.item, t.x, t.y, t.isInstant, e);
                    }, this);
            }),
            (f.updateStagger = function () {
                var t = this.options.stagger;
                return null === t || void 0 === t ? void (this.stagger = 0) : ((this.stagger = a(t)), this.stagger);
            }),
            (f._positionItem = function (t, e, i, n, o) {
                n ? t.goTo(e, i) : (t.stagger(o * this.stagger), t.moveTo(e, i));
            }),
            (f._postLayout = function () {
                this.resizeContainer();
            }),
            (f.resizeContainer = function () {
                var t = this._getOption("resizeContainer");
                if (t) {
                    var e = this._getContainerSize();
                    e && (this._setContainerMeasure(e.width, !0), this._setContainerMeasure(e.height, !1));
                }
            }),
            (f._getContainerSize = d),
            (f._setContainerMeasure = function (t, e) {
                if (void 0 !== t) {
                    var i = this.size;
                    i.isBorderBox && (t += e ? i.paddingLeft + i.paddingRight + i.borderLeftWidth + i.borderRightWidth : i.paddingBottom + i.paddingTop + i.borderTopWidth + i.borderBottomWidth),
                        (t = Math.max(t, 0)),
                        (this.element.style[e ? "width" : "height"] = t + "px");
                }
            }),
            (f._emitCompleteOnItems = function (t, e) {
                function i() {
                    o.dispatchEvent(t + "Complete", null, [e]);
                }
                function n() {
                    s++, s == r && i();
                }
                var o = this,
                    r = e.length;
                if (!e || !r) return void i();
                var s = 0;
                e.forEach(function (e) {
                    e.once(t, n);
                });
            }),
            (f.dispatchEvent = function (t, e, i) {
                var n = e ? [e].concat(i) : i;
                if ((this.emitEvent(t, n), u))
                    if (((this.$element = this.$element || u(this.element)), e)) {
                        var o = u.Event(e);
                        (o.type = t), this.$element.trigger(o, i);
                    } else this.$element.trigger(t, i);
            }),
            (f.ignore = function (t) {
                var e = this.getItem(t);
                e && (e.isIgnored = !0);
            }),
            (f.unignore = function (t) {
                var e = this.getItem(t);
                e && delete e.isIgnored;
            }),
            (f.stamp = function (t) {
                (t = this._find(t)), t && ((this.stamps = this.stamps.concat(t)), t.forEach(this.ignore, this));
            }),
            (f.unstamp = function (t) {
                (t = this._find(t)),
                    t &&
                        t.forEach(function (t) {
                            n.removeFrom(this.stamps, t), this.unignore(t);
                        }, this);
            }),
            (f._find = function (t) {
                return t ? ("string" == typeof t && (t = this.element.querySelectorAll(t)), (t = n.makeArray(t))) : void 0;
            }),
            (f._manageStamps = function () {
                this.stamps && this.stamps.length && (this._getBoundingRect(), this.stamps.forEach(this._manageStamp, this));
            }),
            (f._getBoundingRect = function () {
                var t = this.element.getBoundingClientRect(),
                    e = this.size;
                this._boundingRect = {
                    left: t.left + e.paddingLeft + e.borderLeftWidth,
                    top: t.top + e.paddingTop + e.borderTopWidth,
                    right: t.right - (e.paddingRight + e.borderRightWidth),
                    bottom: t.bottom - (e.paddingBottom + e.borderBottomWidth),
                };
            }),
            (f._manageStamp = d),
            (f._getElementOffset = function (t) {
                var e = t.getBoundingClientRect(),
                    n = this._boundingRect,
                    o = i(t),
                    r = { left: e.left - n.left - o.marginLeft, top: e.top - n.top - o.marginTop, right: n.right - e.right - o.marginRight, bottom: n.bottom - e.bottom - o.marginBottom };
                return r;
            }),
            (f.handleEvent = n.handleEvent),
            (f.bindResize = function () {
                t.addEventListener("resize", this), (this.isResizeBound = !0);
            }),
            (f.unbindResize = function () {
                t.removeEventListener("resize", this), (this.isResizeBound = !1);
            }),
            (f.onresize = function () {
                this.resize();
            }),
            n.debounceMethod(r, "onresize", 100),
            (f.resize = function () {
                this.isResizeBound && this.needsResizeLayout() && this.layout();
            }),
            (f.needsResizeLayout = function () {
                var t = i(this.element),
                    e = this.size && t;
                return e && t.innerWidth !== this.size.innerWidth;
            }),
            (f.addItems = function (t) {
                var e = this._itemize(t);
                return e.length && (this.items = this.items.concat(e)), e;
            }),
            (f.appended = function (t) {
                var e = this.addItems(t);
                e.length && (this.layoutItems(e, !0), this.reveal(e));
            }),
            (f.prepended = function (t) {
                var e = this._itemize(t);
                if (e.length) {
                    var i = this.items.slice(0);
                    (this.items = e.concat(i)), this._resetLayout(), this._manageStamps(), this.layoutItems(e, !0), this.reveal(e), this.layoutItems(i);
                }
            }),
            (f.reveal = function (t) {
                if ((this._emitCompleteOnItems("reveal", t), t && t.length)) {
                    var e = this.updateStagger();
                    t.forEach(function (t, i) {
                        t.stagger(i * e), t.reveal();
                    });
                }
            }),
            (f.hide = function (t) {
                if ((this._emitCompleteOnItems("hide", t), t && t.length)) {
                    var e = this.updateStagger();
                    t.forEach(function (t, i) {
                        t.stagger(i * e), t.hide();
                    });
                }
            }),
            (f.revealItemElements = function (t) {
                var e = this.getItems(t);
                this.reveal(e);
            }),
            (f.hideItemElements = function (t) {
                var e = this.getItems(t);
                this.hide(e);
            }),
            (f.getItem = function (t) {
                for (var e = 0; e < this.items.length; e++) {
                    var i = this.items[e];
                    if (i.element == t) return i;
                }
            }),
            (f.getItems = function (t) {
                t = n.makeArray(t);
                var e = [];
                return (
                    t.forEach(function (t) {
                        var i = this.getItem(t);
                        i && e.push(i);
                    }, this),
                    e
                );
            }),
            (f.remove = function (t) {
                var e = this.getItems(t);
                this._emitCompleteOnItems("remove", e),
                    e &&
                        e.length &&
                        e.forEach(function (t) {
                            t.remove(), n.removeFrom(this.items, t);
                        }, this);
            }),
            (f.destroy = function () {
                var t = this.element.style;
                (t.height = ""),
                    (t.position = ""),
                    (t.width = ""),
                    this.items.forEach(function (t) {
                        t.destroy();
                    }),
                    this.unbindResize();
                var e = this.element.outlayerGUID;
                delete c[e], delete this.element.outlayerGUID, u && u.removeData(this.element, this.constructor.namespace);
            }),
            (r.data = function (t) {
                t = n.getQueryElement(t);
                var e = t && t.outlayerGUID;
                return e && c[e];
            }),
            (r.create = function (t, e) {
                var i = s(r);
                return (
                    (i.defaults = n.extend({}, r.defaults)),
                    n.extend(i.defaults, e),
                    (i.compatOptions = n.extend({}, r.compatOptions)),
                    (i.namespace = t),
                    (i.data = r.data),
                    (i.Item = s(o)),
                    n.htmlInit(i, t),
                    u && u.bridget && u.bridget(t, i),
                    i
                );
            });
        var m = { ms: 1, s: 1e3 };
        return (r.Item = o), r;
    }),
    (function (t, e) {
        "function" == typeof define && define.amd
            ? define(["outlayer/outlayer", "get-size/get-size"], e)
            : "object" == typeof module && module.exports
            ? (module.exports = e(require("outlayer"), require("get-size")))
            : (t.Masonry = e(t.Outlayer, t.getSize));
    })(window, function (t, e) {
        var i = t.create("masonry");
        i.compatOptions.fitWidth = "isFitWidth";
        var n = i.prototype;
        return (
            (n._resetLayout = function () {
                this.getSize(), this._getMeasurement("columnWidth", "outerWidth"), this._getMeasurement("gutter", "outerWidth"), this.measureColumns(), (this.colYs = []);
                for (var t = 0; t < this.cols; t++) this.colYs.push(0);
                (this.maxY = 0), (this.horizontalColIndex = 0);
            }),
            (n.measureColumns = function () {
                if ((this.getContainerWidth(), !this.columnWidth)) {
                    var t = this.items[0],
                        i = t && t.element;
                    this.columnWidth = (i && e(i).outerWidth) || this.containerWidth;
                }
                var n = (this.columnWidth += this.gutter),
                    o = this.containerWidth + this.gutter,
                    r = o / n,
                    s = n - (o % n),
                    a = s && 1 > s ? "round" : "floor";
                (r = Math[a](r)), (this.cols = Math.max(r, 1));
            }),
            (n.getContainerWidth = function () {
                var t = this._getOption("fitWidth"),
                    i = t ? this.element.parentNode : this.element,
                    n = e(i);
                this.containerWidth = n && n.innerWidth;
            }),
            (n._getItemLayoutPosition = function (t) {
                t.getSize();
                var e = t.size.outerWidth % this.columnWidth,
                    i = e && 1 > e ? "round" : "ceil",
                    n = Math[i](t.size.outerWidth / this.columnWidth);
                n = Math.min(n, this.cols);
                for (
                    var o = this.options.horizontalOrder ? "_getHorizontalColPosition" : "_getTopColPosition", r = this[o](n, t), s = { x: this.columnWidth * r.col, y: r.y }, a = r.y + t.size.outerHeight, h = n + r.col, u = r.col;
                    h > u;
                    u++
                )
                    this.colYs[u] = a;
                return s;
            }),
            (n._getTopColPosition = function (t) {
                var e = this._getTopColGroup(t),
                    i = Math.min.apply(Math, e);
                return { col: e.indexOf(i), y: i };
            }),
            (n._getTopColGroup = function (t) {
                if (2 > t) return this.colYs;
                for (var e = [], i = this.cols + 1 - t, n = 0; i > n; n++) e[n] = this._getColGroupY(n, t);
                return e;
            }),
            (n._getColGroupY = function (t, e) {
                if (2 > e) return this.colYs[t];
                var i = this.colYs.slice(t, t + e);
                return Math.max.apply(Math, i);
            }),
            (n._getHorizontalColPosition = function (t, e) {
                var i = this.horizontalColIndex % this.cols,
                    n = t > 1 && i + t > this.cols;
                i = n ? 0 : i;
                var o = e.size.outerWidth && e.size.outerHeight;
                return (this.horizontalColIndex = o ? i + t : this.horizontalColIndex), { col: i, y: this._getColGroupY(i, t) };
            }),
            (n._manageStamp = function (t) {
                var i = e(t),
                    n = this._getElementOffset(t),
                    o = this._getOption("originLeft"),
                    r = o ? n.left : n.right,
                    s = r + i.outerWidth,
                    a = Math.floor(r / this.columnWidth);
                a = Math.max(0, a);
                var h = Math.floor(s / this.columnWidth);
                (h -= s % this.columnWidth ? 0 : 1), (h = Math.min(this.cols - 1, h));
                for (var u = this._getOption("originTop"), d = (u ? n.top : n.bottom) + i.outerHeight, l = a; h >= l; l++) this.colYs[l] = Math.max(d, this.colYs[l]);
            }),
            (n._getContainerSize = function () {
                this.maxY = Math.max.apply(Math, this.colYs);
                var t = { height: this.maxY };
                return this._getOption("fitWidth") && (t.width = this._getContainerFitWidth()), t;
            }),
            (n._getContainerFitWidth = function () {
                for (var t = 0, e = this.cols; --e && 0 === this.colYs[e]; ) t++;
                return (this.cols - t) * this.columnWidth - this.gutter;
            }),
            (n.needsResizeLayout = function () {
                var t = this.containerWidth;
                return this.getContainerWidth(), t != this.containerWidth;
            }),
            i
        );
    });
/*!
 * imagesLoaded PACKAGED v4.1.4
 * JavaScript is all like "You images are done yet or what?"
 * MIT License
 */
!(function (e, t) {
    "function" == typeof define && define.amd ? define("ev-emitter/ev-emitter", t) : "object" == typeof module && module.exports ? (module.exports = t()) : (e.EvEmitter = t());
})("undefined" != typeof window ? window : this, function () {
    function e() {}
    var t = e.prototype;
    return (
        (t.on = function (e, t) {
            if (e && t) {
                var i = (this._events = this._events || {}),
                    n = (i[e] = i[e] || []);
                return n.indexOf(t) == -1 && n.push(t), this;
            }
        }),
        (t.once = function (e, t) {
            if (e && t) {
                this.on(e, t);
                var i = (this._onceEvents = this._onceEvents || {}),
                    n = (i[e] = i[e] || {});
                return (n[t] = !0), this;
            }
        }),
        (t.off = function (e, t) {
            var i = this._events && this._events[e];
            if (i && i.length) {
                var n = i.indexOf(t);
                return n != -1 && i.splice(n, 1), this;
            }
        }),
        (t.emitEvent = function (e, t) {
            var i = this._events && this._events[e];
            if (i && i.length) {
                (i = i.slice(0)), (t = t || []);
                for (var n = this._onceEvents && this._onceEvents[e], o = 0; o < i.length; o++) {
                    var r = i[o],
                        s = n && n[r];
                    s && (this.off(e, r), delete n[r]), r.apply(this, t);
                }
                return this;
            }
        }),
        (t.allOff = function () {
            delete this._events, delete this._onceEvents;
        }),
        e
    );
}),
    (function (e, t) {
        "use strict";
        "function" == typeof define && define.amd
            ? define(["ev-emitter/ev-emitter"], function (i) {
                  return t(e, i);
              })
            : "object" == typeof module && module.exports
            ? (module.exports = t(e, require("ev-emitter")))
            : (e.imagesLoaded = t(e, e.EvEmitter));
    })("undefined" != typeof window ? window : this, function (e, t) {
        function i(e, t) {
            for (var i in t) e[i] = t[i];
            return e;
        }
        function n(e) {
            if (Array.isArray(e)) return e;
            var t = "object" == typeof e && "number" == typeof e.length;
            return t ? d.call(e) : [e];
        }
        function o(e, t, r) {
            if (!(this instanceof o)) return new o(e, t, r);
            var s = e;
            return (
                "string" == typeof e && (s = document.querySelectorAll(e)),
                s
                    ? ((this.elements = n(s)),
                      (this.options = i({}, this.options)),
                      "function" == typeof t ? (r = t) : i(this.options, t),
                      r && this.on("always", r),
                      this.getImages(),
                      h && (this.jqDeferred = new h.Deferred()),
                      void setTimeout(this.check.bind(this)))
                    : void a.error("Bad element for imagesLoaded " + (s || e))
            );
        }
        function r(e) {
            this.img = e;
        }
        function s(e, t) {
            (this.url = e), (this.element = t), (this.img = new Image());
        }
        var h = e.jQuery,
            a = e.console,
            d = Array.prototype.slice;
        (o.prototype = Object.create(t.prototype)),
            (o.prototype.options = {}),
            (o.prototype.getImages = function () {
                (this.images = []), this.elements.forEach(this.addElementImages, this);
            }),
            (o.prototype.addElementImages = function (e) {
                "IMG" == e.nodeName && this.addImage(e), this.options.background === !0 && this.addElementBackgroundImages(e);
                var t = e.nodeType;
                if (t && u[t]) {
                    for (var i = e.querySelectorAll("img"), n = 0; n < i.length; n++) {
                        var o = i[n];
                        this.addImage(o);
                    }
                    if ("string" == typeof this.options.background) {
                        var r = e.querySelectorAll(this.options.background);
                        for (n = 0; n < r.length; n++) {
                            var s = r[n];
                            this.addElementBackgroundImages(s);
                        }
                    }
                }
            });
        var u = { 1: !0, 9: !0, 11: !0 };
        return (
            (o.prototype.addElementBackgroundImages = function (e) {
                var t = getComputedStyle(e);
                if (t)
                    for (var i = /url\((['"])?(.*?)\1\)/gi, n = i.exec(t.backgroundImage); null !== n; ) {
                        var o = n && n[2];
                        o && this.addBackground(o, e), (n = i.exec(t.backgroundImage));
                    }
            }),
            (o.prototype.addImage = function (e) {
                var t = new r(e);
                this.images.push(t);
            }),
            (o.prototype.addBackground = function (e, t) {
                var i = new s(e, t);
                this.images.push(i);
            }),
            (o.prototype.check = function () {
                function e(e, i, n) {
                    setTimeout(function () {
                        t.progress(e, i, n);
                    });
                }
                var t = this;
                return (
                    (this.progressedCount = 0),
                    (this.hasAnyBroken = !1),
                    this.images.length
                        ? void this.images.forEach(function (t) {
                              t.once("progress", e), t.check();
                          })
                        : void this.complete()
                );
            }),
            (o.prototype.progress = function (e, t, i) {
                this.progressedCount++,
                    (this.hasAnyBroken = this.hasAnyBroken || !e.isLoaded),
                    this.emitEvent("progress", [this, e, t]),
                    this.jqDeferred && this.jqDeferred.notify && this.jqDeferred.notify(this, e),
                    this.progressedCount == this.images.length && this.complete(),
                    this.options.debug && a && a.log("progress: " + i, e, t);
            }),
            (o.prototype.complete = function () {
                var e = this.hasAnyBroken ? "fail" : "done";
                if (((this.isComplete = !0), this.emitEvent(e, [this]), this.emitEvent("always", [this]), this.jqDeferred)) {
                    var t = this.hasAnyBroken ? "reject" : "resolve";
                    this.jqDeferred[t](this);
                }
            }),
            (r.prototype = Object.create(t.prototype)),
            (r.prototype.check = function () {
                var e = this.getIsImageComplete();
                return e
                    ? void this.confirm(0 !== this.img.naturalWidth, "naturalWidth")
                    : ((this.proxyImage = new Image()),
                      this.proxyImage.addEventListener("load", this),
                      this.proxyImage.addEventListener("error", this),
                      this.img.addEventListener("load", this),
                      this.img.addEventListener("error", this),
                      void (this.proxyImage.src = this.img.src));
            }),
            (r.prototype.getIsImageComplete = function () {
                return this.img.complete && this.img.naturalWidth;
            }),
            (r.prototype.confirm = function (e, t) {
                (this.isLoaded = e), this.emitEvent("progress", [this, this.img, t]);
            }),
            (r.prototype.handleEvent = function (e) {
                var t = "on" + e.type;
                this[t] && this[t](e);
            }),
            (r.prototype.onload = function () {
                this.confirm(!0, "onload"), this.unbindEvents();
            }),
            (r.prototype.onerror = function () {
                this.confirm(!1, "onerror"), this.unbindEvents();
            }),
            (r.prototype.unbindEvents = function () {
                this.proxyImage.removeEventListener("load", this), this.proxyImage.removeEventListener("error", this), this.img.removeEventListener("load", this), this.img.removeEventListener("error", this);
            }),
            (s.prototype = Object.create(r.prototype)),
            (s.prototype.check = function () {
                this.img.addEventListener("load", this), this.img.addEventListener("error", this), (this.img.src = this.url);
                var e = this.getIsImageComplete();
                e && (this.confirm(0 !== this.img.naturalWidth, "naturalWidth"), this.unbindEvents());
            }),
            (s.prototype.unbindEvents = function () {
                this.img.removeEventListener("load", this), this.img.removeEventListener("error", this);
            }),
            (s.prototype.confirm = function (e, t) {
                (this.isLoaded = e), this.emitEvent("progress", [this, this.element, t]);
            }),
            (o.makeJQueryPlugin = function (t) {
                (t = t || e.jQuery),
                    t &&
                        ((h = t),
                        (h.fn.imagesLoaded = function (e, t) {
                            var i = new o(this, e, t);
                            return i.jqDeferred.promise(h(this));
                        }));
            }),
            o.makeJQueryPlugin(),
            o
        );
    });
/*AOS Animation*/
!(function (e, t) {
    "object" == typeof exports && "object" == typeof module ? (module.exports = t()) : "function" == typeof define && define.amd ? define([], t) : "object" == typeof exports ? (exports.AOS = t()) : (e.AOS = t());
})(this, function () {
    return (function (e) {
        function t(o) {
            if (n[o]) return n[o].exports;
            var i = (n[o] = { exports: {}, id: o, loaded: !1 });
            return e[o].call(i.exports, i, i.exports, t), (i.loaded = !0), i.exports;
        }
        var n = {};
        return (t.m = e), (t.c = n), (t.p = "dist/"), t(0);
    })([
        function (e, t, n) {
            "use strict";
            function o(e) {
                return e && e.__esModule ? e : { default: e };
            }
            var i =
                    Object.assign ||
                    function (e) {
                        for (var t = 1; t < arguments.length; t++) {
                            var n = arguments[t];
                            for (var o in n) Object.prototype.hasOwnProperty.call(n, o) && (e[o] = n[o]);
                        }
                        return e;
                    },
                r = n(1),
                a = (o(r), n(6)),
                u = o(a),
                c = n(7),
                f = o(c),
                s = n(8),
                d = o(s),
                l = n(9),
                p = o(l),
                m = n(10),
                b = o(m),
                v = n(11),
                y = o(v),
                g = n(14),
                h = o(g),
                w = [],
                k = !1,
                x = { offset: 120, delay: 0, easing: "ease", duration: 400, disable: !1, once: !1, startEvent: "DOMContentLoaded", throttleDelay: 99, debounceDelay: 50, disableMutationObserver: !1 },
                j = function () {
                    var e = arguments.length > 0 && void 0 !== arguments[0] && arguments[0];
                    if ((e && (k = !0), k)) return (w = (0, y.default)(w, x)), (0, b.default)(w, x.once), w;
                },
                O = function () {
                    (w = (0, h.default)()), j();
                },
                _ = function () {
                    w.forEach(function (e, t) {
                        e.node.removeAttribute("data-aos"), e.node.removeAttribute("data-aos-easing"), e.node.removeAttribute("data-aos-duration"), e.node.removeAttribute("data-aos-delay");
                    });
                },
                S = function (e) {
                    return e === !0 || ("mobile" === e && p.default.mobile()) || ("phone" === e && p.default.phone()) || ("tablet" === e && p.default.tablet()) || ("function" == typeof e && e() === !0);
                },
                z = function (e) {
                    (x = i(x, e)), (w = (0, h.default)());
                    var t = document.all && !window.atob;
                    return S(x.disable) || t
                        ? _()
                        : (document.querySelector("body").setAttribute("data-aos-easing", x.easing),
                          document.querySelector("body").setAttribute("data-aos-duration", x.duration),
                          document.querySelector("body").setAttribute("data-aos-delay", x.delay),
                          "DOMContentLoaded" === x.startEvent && ["complete", "interactive"].indexOf(document.readyState) > -1
                              ? j(!0)
                              : "load" === x.startEvent
                              ? window.addEventListener(x.startEvent, function () {
                                    j(!0);
                                })
                              : document.addEventListener(x.startEvent, function () {
                                    j(!0);
                                }),
                          window.addEventListener("resize", (0, f.default)(j, x.debounceDelay, !0)),
                          window.addEventListener("orientationchange", (0, f.default)(j, x.debounceDelay, !0)),
                          window.addEventListener(
                              "scroll",
                              (0, u.default)(function () {
                                  (0, b.default)(w, x.once);
                              }, x.throttleDelay)
                          ),
                          x.disableMutationObserver || (0, d.default)("[data-aos]", O),
                          w);
                };
            e.exports = { init: z, refresh: j, refreshHard: O };
        },
        function (e, t) {},
        ,
        ,
        ,
        ,
        function (e, t) {
            (function (t) {
                "use strict";
                function n(e, t, n) {
                    function o(t) {
                        var n = b,
                            o = v;
                        return (b = v = void 0), (k = t), (g = e.apply(o, n));
                    }
                    function r(e) {
                        return (k = e), (h = setTimeout(s, t)), _ ? o(e) : g;
                    }
                    function a(e) {
                        var n = e - w,
                            o = e - k,
                            i = t - n;
                        return S ? j(i, y - o) : i;
                    }
                    function c(e) {
                        var n = e - w,
                            o = e - k;
                        return void 0 === w || n >= t || n < 0 || (S && o >= y);
                    }
                    function s() {
                        var e = O();
                        return c(e) ? d(e) : void (h = setTimeout(s, a(e)));
                    }
                    function d(e) {
                        return (h = void 0), z && b ? o(e) : ((b = v = void 0), g);
                    }
                    function l() {
                        void 0 !== h && clearTimeout(h), (k = 0), (b = w = v = h = void 0);
                    }
                    function p() {
                        return void 0 === h ? g : d(O());
                    }
                    function m() {
                        var e = O(),
                            n = c(e);
                        if (((b = arguments), (v = this), (w = e), n)) {
                            if (void 0 === h) return r(w);
                            if (S) return (h = setTimeout(s, t)), o(w);
                        }
                        return void 0 === h && (h = setTimeout(s, t)), g;
                    }
                    var b,
                        v,
                        y,
                        g,
                        h,
                        w,
                        k = 0,
                        _ = !1,
                        S = !1,
                        z = !0;
                    if ("function" != typeof e) throw new TypeError(f);
                    return (t = u(t) || 0), i(n) && ((_ = !!n.leading), (S = "maxWait" in n), (y = S ? x(u(n.maxWait) || 0, t) : y), (z = "trailing" in n ? !!n.trailing : z)), (m.cancel = l), (m.flush = p), m;
                }
                function o(e, t, o) {
                    var r = !0,
                        a = !0;
                    if ("function" != typeof e) throw new TypeError(f);
                    return i(o) && ((r = "leading" in o ? !!o.leading : r), (a = "trailing" in o ? !!o.trailing : a)), n(e, t, { leading: r, maxWait: t, trailing: a });
                }
                function i(e) {
                    var t = "undefined" == typeof e ? "undefined" : c(e);
                    return !!e && ("object" == t || "function" == t);
                }
                function r(e) {
                    return !!e && "object" == ("undefined" == typeof e ? "undefined" : c(e));
                }
                function a(e) {
                    return "symbol" == ("undefined" == typeof e ? "undefined" : c(e)) || (r(e) && k.call(e) == d);
                }
                function u(e) {
                    if ("number" == typeof e) return e;
                    if (a(e)) return s;
                    if (i(e)) {
                        var t = "function" == typeof e.valueOf ? e.valueOf() : e;
                        e = i(t) ? t + "" : t;
                    }
                    if ("string" != typeof e) return 0 === e ? e : +e;
                    e = e.replace(l, "");
                    var n = m.test(e);
                    return n || b.test(e) ? v(e.slice(2), n ? 2 : 8) : p.test(e) ? s : +e;
                }
                var c =
                        "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
                            ? function (e) {
                                  return typeof e;
                              }
                            : function (e) {
                                  return e && "function" == typeof Symbol && e.constructor === Symbol && e !== Symbol.prototype ? "symbol" : typeof e;
                              },
                    f = "Expected a function",
                    s = NaN,
                    d = "[object Symbol]",
                    l = /^\s+|\s+$/g,
                    p = /^[-+]0x[0-9a-f]+$/i,
                    m = /^0b[01]+$/i,
                    b = /^0o[0-7]+$/i,
                    v = parseInt,
                    y = "object" == ("undefined" == typeof t ? "undefined" : c(t)) && t && t.Object === Object && t,
                    g = "object" == ("undefined" == typeof self ? "undefined" : c(self)) && self && self.Object === Object && self,
                    h = y || g || Function("return this")(),
                    w = Object.prototype,
                    k = w.toString,
                    x = Math.max,
                    j = Math.min,
                    O = function () {
                        return h.Date.now();
                    };
                e.exports = o;
            }.call(
                t,
                (function () {
                    return this;
                })()
            ));
        },
        function (e, t) {
            (function (t) {
                "use strict";
                function n(e, t, n) {
                    function i(t) {
                        var n = b,
                            o = v;
                        return (b = v = void 0), (O = t), (g = e.apply(o, n));
                    }
                    function r(e) {
                        return (O = e), (h = setTimeout(s, t)), _ ? i(e) : g;
                    }
                    function u(e) {
                        var n = e - w,
                            o = e - O,
                            i = t - n;
                        return S ? x(i, y - o) : i;
                    }
                    function f(e) {
                        var n = e - w,
                            o = e - O;
                        return void 0 === w || n >= t || n < 0 || (S && o >= y);
                    }
                    function s() {
                        var e = j();
                        return f(e) ? d(e) : void (h = setTimeout(s, u(e)));
                    }
                    function d(e) {
                        return (h = void 0), z && b ? i(e) : ((b = v = void 0), g);
                    }
                    function l() {
                        void 0 !== h && clearTimeout(h), (O = 0), (b = w = v = h = void 0);
                    }
                    function p() {
                        return void 0 === h ? g : d(j());
                    }
                    function m() {
                        var e = j(),
                            n = f(e);
                        if (((b = arguments), (v = this), (w = e), n)) {
                            if (void 0 === h) return r(w);
                            if (S) return (h = setTimeout(s, t)), i(w);
                        }
                        return void 0 === h && (h = setTimeout(s, t)), g;
                    }
                    var b,
                        v,
                        y,
                        g,
                        h,
                        w,
                        O = 0,
                        _ = !1,
                        S = !1,
                        z = !0;
                    if ("function" != typeof e) throw new TypeError(c);
                    return (t = a(t) || 0), o(n) && ((_ = !!n.leading), (S = "maxWait" in n), (y = S ? k(a(n.maxWait) || 0, t) : y), (z = "trailing" in n ? !!n.trailing : z)), (m.cancel = l), (m.flush = p), m;
                }
                function o(e) {
                    var t = "undefined" == typeof e ? "undefined" : u(e);
                    return !!e && ("object" == t || "function" == t);
                }
                function i(e) {
                    return !!e && "object" == ("undefined" == typeof e ? "undefined" : u(e));
                }
                function r(e) {
                    return "symbol" == ("undefined" == typeof e ? "undefined" : u(e)) || (i(e) && w.call(e) == s);
                }
                function a(e) {
                    if ("number" == typeof e) return e;
                    if (r(e)) return f;
                    if (o(e)) {
                        var t = "function" == typeof e.valueOf ? e.valueOf() : e;
                        e = o(t) ? t + "" : t;
                    }
                    if ("string" != typeof e) return 0 === e ? e : +e;
                    e = e.replace(d, "");
                    var n = p.test(e);
                    return n || m.test(e) ? b(e.slice(2), n ? 2 : 8) : l.test(e) ? f : +e;
                }
                var u =
                        "function" == typeof Symbol && "symbol" == typeof Symbol.iterator
                            ? function (e) {
                                  return typeof e;
                              }
                            : function (e) {
                                  return e && "function" == typeof Symbol && e.constructor === Symbol && e !== Symbol.prototype ? "symbol" : typeof e;
                              },
                    c = "Expected a function",
                    f = NaN,
                    s = "[object Symbol]",
                    d = /^\s+|\s+$/g,
                    l = /^[-+]0x[0-9a-f]+$/i,
                    p = /^0b[01]+$/i,
                    m = /^0o[0-7]+$/i,
                    b = parseInt,
                    v = "object" == ("undefined" == typeof t ? "undefined" : u(t)) && t && t.Object === Object && t,
                    y = "object" == ("undefined" == typeof self ? "undefined" : u(self)) && self && self.Object === Object && self,
                    g = v || y || Function("return this")(),
                    h = Object.prototype,
                    w = h.toString,
                    k = Math.max,
                    x = Math.min,
                    j = function () {
                        return g.Date.now();
                    };
                e.exports = n;
            }.call(
                t,
                (function () {
                    return this;
                })()
            ));
        },
        function (e, t) {
            "use strict";
            function n(e, t) {
                var n = window.document,
                    r = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver,
                    a = new r(o);
                (i = t), a.observe(n.documentElement, { childList: !0, subtree: !0, removedNodes: !0 });
            }
            function o(e) {
                e &&
                    e.forEach(function (e) {
                        var t = Array.prototype.slice.call(e.addedNodes),
                            n = Array.prototype.slice.call(e.removedNodes),
                            o = t.concat(n).filter(function (e) {
                                return e.hasAttribute && e.hasAttribute("data-aos");
                            }).length;
                        o && i();
                    });
            }
            Object.defineProperty(t, "__esModule", { value: !0 });
            var i = function () {};
            t.default = n;
        },
        function (e, t) {
            "use strict";
            function n(e, t) {
                if (!(e instanceof t)) throw new TypeError("Cannot call a class as a function");
            }
            function o() {
                return navigator.userAgent || navigator.vendor || window.opera || "";
            }
            Object.defineProperty(t, "__esModule", { value: !0 });
            var i = (function () {
                    function e(e, t) {
                        for (var n = 0; n < t.length; n++) {
                            var o = t[n];
                            (o.enumerable = o.enumerable || !1), (o.configurable = !0), "value" in o && (o.writable = !0), Object.defineProperty(e, o.key, o);
                        }
                    }
                    return function (t, n, o) {
                        return n && e(t.prototype, n), o && e(t, o), t;
                    };
                })(),
                r = /(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i,
                a = /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i,
                u = /(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|android|ipad|playbook|silk/i,
                c = /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i,
                f = (function () {
                    function e() {
                        n(this, e);
                    }
                    return (
                        i(e, [
                            {
                                key: "phone",
                                value: function () {
                                    var e = o();
                                    return !(!r.test(e) && !a.test(e.substr(0, 4)));
                                },
                            },
                            {
                                key: "mobile",
                                value: function () {
                                    var e = o();
                                    return !(!u.test(e) && !c.test(e.substr(0, 4)));
                                },
                            },
                            {
                                key: "tablet",
                                value: function () {
                                    return this.mobile() && !this.phone();
                                },
                            },
                        ]),
                        e
                    );
                })();
            t.default = new f();
        },
        function (e, t) {
            "use strict";
            Object.defineProperty(t, "__esModule", { value: !0 });
            var n = function (e, t, n) {
                    var o = e.node.getAttribute("data-aos-once");
                    t > e.position ? e.node.classList.add("aos-animate") : "undefined" != typeof o && ("false" === o || (!n && "true" !== o)) && e.node.classList.remove("aos-animate");
                },
                o = function (e, t) {
                    var o = window.pageYOffset,
                        i = window.innerHeight;
                    e.forEach(function (e, r) {
                        n(e, i + o, t);
                    });
                };
            t.default = o;
        },
        function (e, t, n) {
            "use strict";
            function o(e) {
                return e && e.__esModule ? e : { default: e };
            }
            Object.defineProperty(t, "__esModule", { value: !0 });
            var i = n(12),
                r = o(i),
                a = function (e, t) {
                    return (
                        e.forEach(function (e, n) {
                            e.node.classList.add("aos-init"), (e.position = (0, r.default)(e.node, t.offset));
                        }),
                        e
                    );
                };
            t.default = a;
        },
        function (e, t, n) {
            "use strict";
            function o(e) {
                return e && e.__esModule ? e : { default: e };
            }
            Object.defineProperty(t, "__esModule", { value: !0 });
            var i = n(13),
                r = o(i),
                a = function (e, t) {
                    var n = 0,
                        o = 0,
                        i = window.innerHeight,
                        a = { offset: e.getAttribute("data-aos-offset"), anchor: e.getAttribute("data-aos-anchor"), anchorPlacement: e.getAttribute("data-aos-anchor-placement") };
                    switch ((a.offset && !isNaN(a.offset) && (o = parseInt(a.offset)), a.anchor && document.querySelectorAll(a.anchor) && (e = document.querySelectorAll(a.anchor)[0]), (n = (0, r.default)(e).top), a.anchorPlacement)) {
                        case "top-bottom":
                            break;
                        case "center-bottom":
                            n += e.offsetHeight / 2;
                            break;
                        case "bottom-bottom":
                            n += e.offsetHeight;
                            break;
                        case "top-center":
                            n += i / 2;
                            break;
                        case "bottom-center":
                            n += i / 2 + e.offsetHeight;
                            break;
                        case "center-center":
                            n += i / 2 + e.offsetHeight / 2;
                            break;
                        case "top-top":
                            n += i;
                            break;
                        case "bottom-top":
                            n += e.offsetHeight + i;
                            break;
                        case "center-top":
                            n += e.offsetHeight / 2 + i;
                    }
                    return a.anchorPlacement || a.offset || isNaN(t) || (o = t), n + o;
                };
            t.default = a;
        },
        function (e, t) {
            "use strict";
            Object.defineProperty(t, "__esModule", { value: !0 });
            var n = function (e) {
                for (var t = 0, n = 0; e && !isNaN(e.offsetLeft) && !isNaN(e.offsetTop); ) (t += e.offsetLeft - ("BODY" != e.tagName ? e.scrollLeft : 0)), (n += e.offsetTop - ("BODY" != e.tagName ? e.scrollTop : 0)), (e = e.offsetParent);
                return { top: n, left: t };
            };
            t.default = n;
        },
        function (e, t) {
            "use strict";
            Object.defineProperty(t, "__esModule", { value: !0 });
            var n = function (e) {
                return (
                    (e = e || document.querySelectorAll("[data-aos]")),
                    Array.prototype.map.call(e, function (e) {
                        return { node: e };
                    })
                );
            };
            t.default = n;
        },
    ]);
});
/*!
 * The Final Countdown for jQuery v2.2.0 (http://hilios.github.io/jQuery.countdown/)
 */
!(function (a) {
    "use strict";
    "function" == typeof define && define.amd ? define(["jquery"], a) : a(jQuery);
})(function (a) {
    "use strict";
    function b(a) {
        if (a instanceof Date) return a;
        if (String(a).match(g)) return String(a).match(/^[0-9]*$/) && (a = Number(a)), String(a).match(/\-/) && (a = String(a).replace(/\-/g, "http://themegeniuslab.com/")), new Date(a);
        throw new Error("Couldn't cast `" + a + "` to a date object.");
    }
    function c(a) {
        var b = a.toString().replace(/([.?*+^$[\]\\(){}|-])/g, "\\$1");
        return new RegExp(b);
    }
    function d(a) {
        return function (b) {
            var d = b.match(/%(-|!)?[A-Z]{1}(:[^;]+;)?/gi);
            if (d)
                for (var f = 0, g = d.length; f < g; ++f) {
                    var h = d[f].match(/%(-|!)?([a-zA-Z]{1})(:[^;]+;)?/),
                        j = c(h[0]),
                        k = h[1] || "",
                        l = h[3] || "",
                        m = null;
                    (h = h[2]), i.hasOwnProperty(h) && ((m = i[h]), (m = Number(a[m]))), null !== m && ("!" === k && (m = e(l, m)), "" === k && m < 10 && (m = "0" + m.toString()), (b = b.replace(j, m.toString())));
                }
            return (b = b.replace(/%%/, "%"));
        };
    }
    function e(a, b) {
        var c = "s",
            d = "";
        return a && ((a = a.replace(/(:|;|\s)/gi, "").split(/\,/)), 1 === a.length ? (c = a[0]) : ((d = a[0]), (c = a[1]))), Math.abs(b) > 1 ? c : d;
    }
    var f = [],
        g = [],
        h = { precision: 100, elapse: !1, defer: !1 };
    g.push(/^[0-9]*$/.source), g.push(/([0-9]{1,2}\/){2}[0-9]{4}( [0-9]{1,2}(:[0-9]{2}){2})?/.source), g.push(/[0-9]{4}([\/\-][0-9]{1,2}){2}( [0-9]{1,2}(:[0-9]{2}){2})?/.source), (g = new RegExp(g.join("|")));
    var i = { Y: "years", m: "months", n: "daysToMonth", d: "daysToWeek", w: "weeks", W: "weeksToMonth", H: "hours", M: "minutes", S: "seconds", D: "totalDays", I: "totalHours", N: "totalMinutes", T: "totalSeconds" },
        j = function (b, c, d) {
            (this.el = b),
                (this.$el = a(b)),
                (this.interval = null),
                (this.offset = {}),
                (this.options = a.extend({}, h)),
                (this.firstTick = !0),
                (this.instanceNumber = f.length),
                f.push(this),
                this.$el.data("countdown-instance", this.instanceNumber),
                d && ("function" == typeof d ? (this.$el.on("update.countdown", d), this.$el.on("stoped.countdown", d), this.$el.on("finish.countdown", d)) : (this.options = a.extend({}, h, d))),
                this.setFinalDate(c),
                this.options.defer === !1 && this.start();
        };
    a.extend(j.prototype, {
        start: function () {
            null !== this.interval && clearInterval(this.interval);
            var a = this;
            this.update(),
                (this.interval = setInterval(function () {
                    a.update.call(a);
                }, this.options.precision));
        },
        stop: function () {
            clearInterval(this.interval), (this.interval = null), this.dispatchEvent("stoped");
        },
        toggle: function () {
            this.interval ? this.stop() : this.start();
        },
        pause: function () {
            this.stop();
        },
        resume: function () {
            this.start();
        },
        remove: function () {
            this.stop.call(this), (f[this.instanceNumber] = null), delete this.$el.data().countdownInstance;
        },
        setFinalDate: function (a) {
            this.finalDate = b(a);
        },
        update: function () {
            if (0 === this.$el.closest("html").length) return void this.remove();
            var a,
                b = new Date();
            return (
                (a = this.finalDate.getTime() - b.getTime()),
                (a = Math.ceil(a / 1e3)),
                (a = !this.options.elapse && a < 0 ? 0 : Math.abs(a)),
                this.totalSecsLeft === a || this.firstTick
                    ? void (this.firstTick = !1)
                    : ((this.totalSecsLeft = a),
                      (this.elapsed = b >= this.finalDate),
                      (this.offset = {
                          seconds: this.totalSecsLeft % 60,
                          minutes: Math.floor(this.totalSecsLeft / 60) % 60,
                          hours: Math.floor(this.totalSecsLeft / 60 / 60) % 24,
                          days: Math.floor(this.totalSecsLeft / 60 / 60 / 24) % 7,
                          daysToWeek: Math.floor(this.totalSecsLeft / 60 / 60 / 24) % 7,
                          daysToMonth: Math.floor((this.totalSecsLeft / 60 / 60 / 24) % 30.4368),
                          weeks: Math.floor(this.totalSecsLeft / 60 / 60 / 24 / 7),
                          weeksToMonth: Math.floor(this.totalSecsLeft / 60 / 60 / 24 / 7) % 4,
                          months: Math.floor(this.totalSecsLeft / 60 / 60 / 24 / 30.4368),
                          years: Math.abs(this.finalDate.getFullYear() - b.getFullYear()),
                          totalDays: Math.floor(this.totalSecsLeft / 60 / 60 / 24),
                          totalHours: Math.floor(this.totalSecsLeft / 60 / 60),
                          totalMinutes: Math.floor(this.totalSecsLeft / 60),
                          totalSeconds: this.totalSecsLeft,
                      }),
                      void (this.options.elapse || 0 !== this.totalSecsLeft ? this.dispatchEvent("update") : (this.stop(), this.dispatchEvent("finish"))))
            );
        },
        dispatchEvent: function (b) {
            var c = a.Event(b + ".countdown");
            (c.finalDate = this.finalDate), (c.elapsed = this.elapsed), (c.offset = a.extend({}, this.offset)), (c.strftime = d(this.offset)), this.$el.trigger(c);
        },
    }),
        (a.fn.countdown = function () {
            var b = Array.prototype.slice.call(arguments, 0);
            return this.each(function () {
                var c = a(this).data("countdown-instance");
                if (void 0 !== c) {
                    var d = f[c],
                        e = b[0];
                    j.prototype.hasOwnProperty(e)
                        ? d[e].apply(d, b.slice(1))
                        : null === String(e).match(/^[$A-Z_][0-9A-Z_$]*$/i)
                        ? (d.setFinalDate.call(d, e), d.start())
                        : a.error("Method %s does not exist on jQuery.countdown".replace(/\%s/gi, e));
                } else new j(this, b[0], b[1]);
            });
        });
});
/*! lightgallery - v1.6.11 - 2018-05-22
 * http://sachinchoolur.github.io/lightGallery/
 * Copyright (c) 2018 Sachin N; Licensed GPLv3 */
!(function (a, b) {
    "function" == typeof define && define.amd
        ? define(["jquery"], function (a) {
              return b(a);
          })
        : "object" == typeof module && module.exports
        ? (module.exports = b(require("jquery")))
        : b(a.jQuery);
})(this, function (a) {
    !(function () {
        "use strict";
        function b(b, d) {
            if (((this.el = b), (this.$el = a(b)), (this.s = a.extend({}, c, d)), this.s.dynamic && "undefined" !== this.s.dynamicEl && this.s.dynamicEl.constructor === Array && !this.s.dynamicEl.length))
                throw "When using dynamic mode, you must also define dynamicEl as an Array.";
            return (
                (this.modules = {}),
                (this.lGalleryOn = !1),
                (this.lgBusy = !1),
                (this.hideBartimeout = !1),
                (this.isTouch = "ontouchstart" in document.documentElement),
                this.s.slideEndAnimatoin && (this.s.hideControlOnEnd = !1),
                this.s.dynamic
                    ? (this.$items = this.s.dynamicEl)
                    : "this" === this.s.selector
                    ? (this.$items = this.$el)
                    : "" !== this.s.selector
                    ? this.s.selectWithin
                        ? (this.$items = a(this.s.selectWithin).find(this.s.selector))
                        : (this.$items = this.$el.find(a(this.s.selector)))
                    : (this.$items = this.$el.children()),
                (this.$slide = ""),
                (this.$outer = ""),
                this.init(),
                this
            );
        }
        var c = {
            mode: "lg-slide",
            cssEasing: "ease",
            easing: "linear",
            speed: 600,
            height: "100%",
            width: "100%",
            addClass: "",
            startClass: "lg-start-zoom",
            backdropDuration: 150,
            hideBarsDelay: 6e3,
            useLeft: !1,
            closable: !0,
            loop: !0,
            escKey: !0,
            keyPress: !0,
            controls: !0,
            slideEndAnimatoin: !0,
            hideControlOnEnd: !1,
            mousewheel: !0,
            getCaptionFromTitleOrAlt: !0,
            appendSubHtmlTo: ".lg-sub-html",
            subHtmlSelectorRelative: !1,
            preload: 1,
            showAfterLoad: !0,
            selector: "",
            selectWithin: "",
            nextHtml: "",
            prevHtml: "",
            index: !1,
            iframeMaxWidth: "100%",
            download: !0,
            counter: !0,
            appendCounterTo: ".lg-toolbar",
            swipeThreshold: 50,
            enableSwipe: !0,
            enableDrag: !0,
            dynamic: !1,
            dynamicEl: [],
            galleryId: 1,
        };
        (b.prototype.init = function () {
            var b = this;
            b.s.preload > b.$items.length && (b.s.preload = b.$items.length);
            var c = window.location.hash;
            c.indexOf("lg=" + this.s.galleryId) > 0 &&
                ((b.index = parseInt(c.split("&slide=")[1], 10)),
                a("body").addClass("lg-from-hash"),
                a("body").hasClass("lg-on") ||
                    (setTimeout(function () {
                        b.build(b.index);
                    }),
                    a("body").addClass("lg-on"))),
                b.s.dynamic
                    ? (b.$el.trigger("onBeforeOpen.lg"),
                      (b.index = b.s.index || 0),
                      a("body").hasClass("lg-on") ||
                          setTimeout(function () {
                              b.build(b.index), a("body").addClass("lg-on");
                          }))
                    : b.$items.on("click.lgcustom", function (c) {
                          try {
                              c.preventDefault(), c.preventDefault();
                          } catch (a) {
                              c.returnValue = !1;
                          }
                          b.$el.trigger("onBeforeOpen.lg"), (b.index = b.s.index || b.$items.index(this)), a("body").hasClass("lg-on") || (b.build(b.index), a("body").addClass("lg-on"));
                      });
        }),
            (b.prototype.build = function (b) {
                var c = this;
                c.structure(),
                    a.each(a.fn.lightGallery.modules, function (b) {
                        c.modules[b] = new a.fn.lightGallery.modules[b](c.el);
                    }),
                    c.slide(b, !1, !1, !1),
                    c.s.keyPress && c.keyPress(),
                    c.$items.length > 1
                        ? (c.arrow(),
                          setTimeout(function () {
                              c.enableDrag(), c.enableSwipe();
                          }, 50),
                          c.s.mousewheel && c.mousewheel())
                        : c.$slide.on("click.lg", function () {
                              c.$el.trigger("onSlideClick.lg");
                          }),
                    c.counter(),
                    c.closeGallery(),
                    c.$el.trigger("onAfterOpen.lg"),
                    c.$outer.on("mousemove.lg click.lg touchstart.lg", function () {
                        c.$outer.removeClass("lg-hide-items"),
                            clearTimeout(c.hideBartimeout),
                            (c.hideBartimeout = setTimeout(function () {
                                c.$outer.addClass("lg-hide-items");
                            }, c.s.hideBarsDelay));
                    }),
                    c.$outer.trigger("mousemove.lg");
            }),
            (b.prototype.structure = function () {
                var b,
                    c = "",
                    d = "",
                    e = 0,
                    f = "",
                    g = this;
                for (a("body").append('<div class="lg-backdrop"></div>'), a(".lg-backdrop").css("transition-duration", this.s.backdropDuration + "ms"), e = 0; e < this.$items.length; e++) c += '<div class="lg-item"></div>';
                if (
                    (this.s.controls && this.$items.length > 1 && (d = '<div class="lg-actions"><button class="lg-prev lg-icon">' + this.s.prevHtml + '</button><button class="lg-next lg-icon">' + this.s.nextHtml + "</button></div>"),
                    ".lg-sub-html" === this.s.appendSubHtmlTo && (f = '<div class="lg-sub-html"></div>'),
                    (b =
                        '<div class="lg-outer ' +
                        this.s.addClass +
                        " " +
                        this.s.startClass +
                        '"><div class="lg" style="width:' +
                        this.s.width +
                        "; height:" +
                        this.s.height +
                        '"><div class="lg-inner">' +
                        c +
                        '</div><div class="lg-toolbar lg-group"><span class="lg-close lg-icon"></span></div>' +
                        d +
                        f +
                        "</div></div>"),
                    a("body").append(b),
                    (this.$outer = a(".lg-outer")),
                    (this.$slide = this.$outer.find(".lg-item")),
                    this.s.useLeft ? (this.$outer.addClass("lg-use-left"), (this.s.mode = "lg-slide")) : this.$outer.addClass("lg-use-css3"),
                    g.setTop(),
                    a(window).on("resize.lg orientationchange.lg", function () {
                        setTimeout(function () {
                            g.setTop();
                        }, 100);
                    }),
                    this.$slide.eq(this.index).addClass("lg-current"),
                    this.doCss() ? this.$outer.addClass("lg-css3") : (this.$outer.addClass("lg-css"), (this.s.speed = 0)),
                    this.$outer.addClass(this.s.mode),
                    this.s.enableDrag && this.$items.length > 1 && this.$outer.addClass("lg-grab"),
                    this.s.showAfterLoad && this.$outer.addClass("lg-show-after-load"),
                    this.doCss())
                ) {
                    var h = this.$outer.find(".lg-inner");
                    h.css("transition-timing-function", this.s.cssEasing), h.css("transition-duration", this.s.speed + "ms");
                }
                setTimeout(function () {
                    a(".lg-backdrop").addClass("in");
                }),
                    setTimeout(function () {
                        g.$outer.addClass("lg-visible");
                    }, this.s.backdropDuration),
                    this.s.download && this.$outer.find(".lg-toolbar").append('<a id="lg-download" target="_blank" download class="lg-download lg-icon"></a>'),
                    (this.prevScrollTop = a(window).scrollTop());
            }),
            (b.prototype.setTop = function () {
                if ("100%" !== this.s.height) {
                    var b = a(window).height(),
                        c = (b - parseInt(this.s.height, 10)) / 2,
                        d = this.$outer.find(".lg");
                    b >= parseInt(this.s.height, 10) ? d.css("top", c + "px") : d.css("top", "0px");
                }
            }),
            (b.prototype.doCss = function () {
                return !!(function () {
                    var a = ["transition", "MozTransition", "WebkitTransition", "OTransition", "msTransition", "KhtmlTransition"],
                        b = document.documentElement,
                        c = 0;
                    for (c = 0; c < a.length; c++) if (a[c] in b.style) return !0;
                })();
            }),
            (b.prototype.isVideo = function (a, b) {
                var c;
                if (((c = this.s.dynamic ? this.s.dynamicEl[b].html : this.$items.eq(b).attr("data-html")), !a))
                    return c
                        ? { html5: !0 }
                        : (console.error(
                              "lightGallery :- data-src is not pvovided on slide item " +
                                  (b + 1) +
                                  ". Please make sure the selector property is properly configured. More info - http://sachinchoolur.github.io/lightGallery/demos/html-markup.html"
                          ),
                          !1);
                var d = a.match(/\/\/(?:www\.)?youtu(?:\.be|be\.com|be-nocookie\.com)\/(?:watch\?v=|embed\/)?([a-z0-9\-\_\%]+)/i),
                    e = a.match(/\/\/(?:www\.)?vimeo.com\/([0-9a-z\-_]+)/i),
                    f = a.match(/\/\/(?:www\.)?dai.ly\/([0-9a-z\-_]+)/i),
                    g = a.match(/\/\/(?:www\.)?(?:vk\.com|vkontakte\.ru)\/(?:video_ext\.php\?)(.*)/i);
                return d ? { youtube: d } : e ? { vimeo: e } : f ? { dailymotion: f } : g ? { vk: g } : void 0;
            }),
            (b.prototype.counter = function () {
                this.s.counter && a(this.s.appendCounterTo).append('<div id="lg-counter"><span id="lg-counter-current">' + (parseInt(this.index, 10) + 1) + '</span> / <span id="lg-counter-all">' + this.$items.length + "</span></div>");
            }),
            (b.prototype.addHtml = function (b) {
                var c,
                    d,
                    e = null;
                if (
                    (this.s.dynamic
                        ? this.s.dynamicEl[b].subHtmlUrl
                            ? (c = this.s.dynamicEl[b].subHtmlUrl)
                            : (e = this.s.dynamicEl[b].subHtml)
                        : ((d = this.$items.eq(b)),
                          d.attr("data-sub-html-url") ? (c = d.attr("data-sub-html-url")) : ((e = d.attr("data-sub-html")), this.s.getCaptionFromTitleOrAlt && !e && (e = d.attr("title") || d.find("img").first().attr("alt")))),
                    !c)
                )
                    if (void 0 !== e && null !== e) {
                        var f = e.substring(0, 1);
                        ("." !== f && "#" !== f) || (e = this.s.subHtmlSelectorRelative && !this.s.dynamic ? d.find(e).html() : a(e).html());
                    } else e = "";
                ".lg-sub-html" === this.s.appendSubHtmlTo ? (c ? this.$outer.find(this.s.appendSubHtmlTo).load(c) : this.$outer.find(this.s.appendSubHtmlTo).html(e)) : c ? this.$slide.eq(b).load(c) : this.$slide.eq(b).append(e),
                    void 0 !== e && null !== e && ("" === e ? this.$outer.find(this.s.appendSubHtmlTo).addClass("lg-empty-html") : this.$outer.find(this.s.appendSubHtmlTo).removeClass("lg-empty-html")),
                    this.$el.trigger("onAfterAppendSubHtml.lg", [b]);
            }),
            (b.prototype.preload = function (a) {
                var b = 1,
                    c = 1;
                for (b = 1; b <= this.s.preload && !(b >= this.$items.length - a); b++) this.loadContent(a + b, !1, 0);
                for (c = 1; c <= this.s.preload && !(a - c < 0); c++) this.loadContent(a - c, !1, 0);
            }),
            (b.prototype.loadContent = function (b, c, d) {
                var e,
                    f,
                    g,
                    h,
                    i,
                    j,
                    k = this,
                    l = !1,
                    m = function (b) {
                        for (var c = [], d = [], e = 0; e < b.length; e++) {
                            var g = b[e].split(" ");
                            "" === g[0] && g.splice(0, 1), d.push(g[0]), c.push(g[1]);
                        }
                        for (var h = a(window).width(), i = 0; i < c.length; i++)
                            if (parseInt(c[i], 10) > h) {
                                f = d[i];
                                break;
                            }
                    };
                if (k.s.dynamic) {
                    if ((k.s.dynamicEl[b].poster && ((l = !0), (g = k.s.dynamicEl[b].poster)), (j = k.s.dynamicEl[b].html), (f = k.s.dynamicEl[b].src), k.s.dynamicEl[b].responsive)) {
                        m(k.s.dynamicEl[b].responsive.split(","));
                    }
                    (h = k.s.dynamicEl[b].srcset), (i = k.s.dynamicEl[b].sizes);
                } else {
                    if (
                        (k.$items.eq(b).attr("data-poster") && ((l = !0), (g = k.$items.eq(b).attr("data-poster"))),
                        (j = k.$items.eq(b).attr("data-html")),
                        (f = k.$items.eq(b).attr("href") || k.$items.eq(b).attr("data-src")),
                        k.$items.eq(b).attr("data-responsive"))
                    ) {
                        m(k.$items.eq(b).attr("data-responsive").split(","));
                    }
                    (h = k.$items.eq(b).attr("data-srcset")), (i = k.$items.eq(b).attr("data-sizes"));
                }
                var n = !1;
                k.s.dynamic ? k.s.dynamicEl[b].iframe && (n = !0) : "true" === k.$items.eq(b).attr("data-iframe") && (n = !0);
                var o = k.isVideo(f, b);
                if (!k.$slide.eq(b).hasClass("lg-loaded")) {
                    if (n)
                        k.$slide
                            .eq(b)
                            .prepend(
                                '<div class="lg-video-cont lg-has-iframe" style="max-width:' +
                                    k.s.iframeMaxWidth +
                                    '"><div class="lg-video"><iframe class="lg-object" frameborder="0" src="' +
                                    f +
                                    '"  allowfullscreen="true"></iframe></div></div>'
                            );
                    else if (l) {
                        var p = "";
                        (p = o && o.youtube ? "lg-has-youtube" : o && o.vimeo ? "lg-has-vimeo" : "lg-has-html5"),
                            k.$slide.eq(b).prepend('<div class="lg-video-cont ' + p + ' "><div class="lg-video"><span class="lg-video-play"></span><img class="lg-object lg-has-poster" src="' + g + '" /></div></div>');
                    } else
                        o
                            ? (k.$slide.eq(b).prepend('<div class="lg-video-cont "><div class="lg-video"></div></div>'), k.$el.trigger("hasVideo.lg", [b, f, j]))
                            : k.$slide.eq(b).prepend('<div class="lg-img-wrap"><img class="lg-object lg-image" src="' + f + '" /></div>');
                    if ((k.$el.trigger("onAferAppendSlide.lg", [b]), (e = k.$slide.eq(b).find(".lg-object")), i && e.attr("sizes", i), h)) {
                        e.attr("srcset", h);
                        try {
                            picturefill({ elements: [e[0]] });
                        } catch (a) {
                            console.warn("lightGallery :- If you want srcset to be supported for older browser please include picturefil version 2 javascript library in your document.");
                        }
                    }
                    ".lg-sub-html" !== this.s.appendSubHtmlTo && k.addHtml(b), k.$slide.eq(b).addClass("lg-loaded");
                }
                k.$slide
                    .eq(b)
                    .find(".lg-object")
                    .on("load.lg error.lg", function () {
                        var c = 0;
                        d && !a("body").hasClass("lg-from-hash") && (c = d),
                            setTimeout(function () {
                                k.$slide.eq(b).addClass("lg-complete"), k.$el.trigger("onSlideItemLoad.lg", [b, d || 0]);
                            }, c);
                    }),
                    o && o.html5 && !l && k.$slide.eq(b).addClass("lg-complete"),
                    !0 === c &&
                        (k.$slide.eq(b).hasClass("lg-complete")
                            ? k.preload(b)
                            : k.$slide
                                  .eq(b)
                                  .find(".lg-object")
                                  .on("load.lg error.lg", function () {
                                      k.preload(b);
                                  }));
            }),
            (b.prototype.slide = function (b, c, d, e) {
                var f = this.$outer.find(".lg-current").index(),
                    g = this;
                if (!g.lGalleryOn || f !== b) {
                    var h = this.$slide.length,
                        i = g.lGalleryOn ? this.s.speed : 0;
                    if (!g.lgBusy) {
                        if (this.s.download) {
                            var j;
                            (j = g.s.dynamic
                                ? !1 !== g.s.dynamicEl[b].downloadUrl && (g.s.dynamicEl[b].downloadUrl || g.s.dynamicEl[b].src)
                                : "false" !== g.$items.eq(b).attr("data-download-url") && (g.$items.eq(b).attr("data-download-url") || g.$items.eq(b).attr("href") || g.$items.eq(b).attr("data-src"))),
                                j ? (a("#lg-download").attr("href", j), g.$outer.removeClass("lg-hide-download")) : g.$outer.addClass("lg-hide-download");
                        }
                        if (
                            (this.$el.trigger("onBeforeSlide.lg", [f, b, c, d]),
                            (g.lgBusy = !0),
                            clearTimeout(g.hideBartimeout),
                            ".lg-sub-html" === this.s.appendSubHtmlTo &&
                                setTimeout(function () {
                                    g.addHtml(b);
                                }, i),
                            this.arrowDisable(b),
                            e || (b < f ? (e = "prev") : b > f && (e = "next")),
                            c)
                        ) {
                            this.$slide.removeClass("lg-prev-slide lg-current lg-next-slide");
                            var k, l;
                            h > 2 ? ((k = b - 1), (l = b + 1), 0 === b && f === h - 1 ? ((l = 0), (k = h - 1)) : b === h - 1 && 0 === f && ((l = 0), (k = h - 1))) : ((k = 0), (l = 1)),
                                "prev" === e ? g.$slide.eq(l).addClass("lg-next-slide") : g.$slide.eq(k).addClass("lg-prev-slide"),
                                g.$slide.eq(b).addClass("lg-current");
                        } else
                            g.$outer.addClass("lg-no-trans"),
                                this.$slide.removeClass("lg-prev-slide lg-next-slide"),
                                "prev" === e ? (this.$slide.eq(b).addClass("lg-prev-slide"), this.$slide.eq(f).addClass("lg-next-slide")) : (this.$slide.eq(b).addClass("lg-next-slide"), this.$slide.eq(f).addClass("lg-prev-slide")),
                                setTimeout(function () {
                                    g.$slide.removeClass("lg-current"), g.$slide.eq(b).addClass("lg-current"), g.$outer.removeClass("lg-no-trans");
                                }, 50);
                        g.lGalleryOn
                            ? (setTimeout(function () {
                                  g.loadContent(b, !0, 0);
                              }, this.s.speed + 50),
                              setTimeout(function () {
                                  (g.lgBusy = !1), g.$el.trigger("onAfterSlide.lg", [f, b, c, d]);
                              }, this.s.speed))
                            : (g.loadContent(b, !0, g.s.backdropDuration), (g.lgBusy = !1), g.$el.trigger("onAfterSlide.lg", [f, b, c, d])),
                            (g.lGalleryOn = !0),
                            this.s.counter && a("#lg-counter-current").text(b + 1);
                    }
                    g.index = b;
                }
            }),
            (b.prototype.goToNextSlide = function (a) {
                var b = this,
                    c = b.s.loop;
                a && b.$slide.length < 3 && (c = !1),
                    b.lgBusy ||
                        (b.index + 1 < b.$slide.length
                            ? (b.index++, b.$el.trigger("onBeforeNextSlide.lg", [b.index]), b.slide(b.index, a, !1, "next"))
                            : c
                            ? ((b.index = 0), b.$el.trigger("onBeforeNextSlide.lg", [b.index]), b.slide(b.index, a, !1, "next"))
                            : b.s.slideEndAnimatoin &&
                              !a &&
                              (b.$outer.addClass("lg-right-end"),
                              setTimeout(function () {
                                  b.$outer.removeClass("lg-right-end");
                              }, 400)));
            }),
            (b.prototype.goToPrevSlide = function (a) {
                var b = this,
                    c = b.s.loop;
                a && b.$slide.length < 3 && (c = !1),
                    b.lgBusy ||
                        (b.index > 0
                            ? (b.index--, b.$el.trigger("onBeforePrevSlide.lg", [b.index, a]), b.slide(b.index, a, !1, "prev"))
                            : c
                            ? ((b.index = b.$items.length - 1), b.$el.trigger("onBeforePrevSlide.lg", [b.index, a]), b.slide(b.index, a, !1, "prev"))
                            : b.s.slideEndAnimatoin &&
                              !a &&
                              (b.$outer.addClass("lg-left-end"),
                              setTimeout(function () {
                                  b.$outer.removeClass("lg-left-end");
                              }, 400)));
            }),
            (b.prototype.keyPress = function () {
                var b = this;
                this.$items.length > 1 &&
                    a(window).on("keyup.lg", function (a) {
                        b.$items.length > 1 && (37 === a.keyCode && (a.preventDefault(), b.goToPrevSlide()), 39 === a.keyCode && (a.preventDefault(), b.goToNextSlide()));
                    }),
                    a(window).on("keydown.lg", function (a) {
                        !0 === b.s.escKey && 27 === a.keyCode && (a.preventDefault(), b.$outer.hasClass("lg-thumb-open") ? b.$outer.removeClass("lg-thumb-open") : b.destroy());
                    });
            }),
            (b.prototype.arrow = function () {
                var a = this;
                this.$outer.find(".lg-prev").on("click.lg", function () {
                    a.goToPrevSlide();
                }),
                    this.$outer.find(".lg-next").on("click.lg", function () {
                        a.goToNextSlide();
                    });
            }),
            (b.prototype.arrowDisable = function (a) {
                !this.s.loop &&
                    this.s.hideControlOnEnd &&
                    (a + 1 < this.$slide.length ? this.$outer.find(".lg-next").removeAttr("disabled").removeClass("disabled") : this.$outer.find(".lg-next").attr("disabled", "disabled").addClass("disabled"),
                    a > 0 ? this.$outer.find(".lg-prev").removeAttr("disabled").removeClass("disabled") : this.$outer.find(".lg-prev").attr("disabled", "disabled").addClass("disabled"));
            }),
            (b.prototype.setTranslate = function (a, b, c) {
                this.s.useLeft ? a.css("left", b) : a.css({ transform: "translate3d(" + b + "px, " + c + "px, 0px)" });
            }),
            (b.prototype.touchMove = function (b, c) {
                var d = c - b;
                Math.abs(d) > 15 &&
                    (this.$outer.addClass("lg-dragging"),
                    this.setTranslate(this.$slide.eq(this.index), d, 0),
                    this.setTranslate(a(".lg-prev-slide"), -this.$slide.eq(this.index).width() + d, 0),
                    this.setTranslate(a(".lg-next-slide"), this.$slide.eq(this.index).width() + d, 0));
            }),
            (b.prototype.touchEnd = function (a) {
                var b = this;
                "lg-slide" !== b.s.mode && b.$outer.addClass("lg-slide"),
                    this.$slide.not(".lg-current, .lg-prev-slide, .lg-next-slide").css("opacity", "0"),
                    setTimeout(function () {
                        b.$outer.removeClass("lg-dragging"),
                            a < 0 && Math.abs(a) > b.s.swipeThreshold ? b.goToNextSlide(!0) : a > 0 && Math.abs(a) > b.s.swipeThreshold ? b.goToPrevSlide(!0) : Math.abs(a) < 5 && b.$el.trigger("onSlideClick.lg"),
                            b.$slide.removeAttr("style");
                    }),
                    setTimeout(function () {
                        b.$outer.hasClass("lg-dragging") || "lg-slide" === b.s.mode || b.$outer.removeClass("lg-slide");
                    }, b.s.speed + 100);
            }),
            (b.prototype.enableSwipe = function () {
                var a = this,
                    b = 0,
                    c = 0,
                    d = !1;
                a.s.enableSwipe &&
                    a.doCss() &&
                    (a.$slide.on("touchstart.lg", function (c) {
                        a.$outer.hasClass("lg-zoomed") || a.lgBusy || (c.preventDefault(), a.manageSwipeClass(), (b = c.originalEvent.targetTouches[0].pageX));
                    }),
                    a.$slide.on("touchmove.lg", function (e) {
                        a.$outer.hasClass("lg-zoomed") || (e.preventDefault(), (c = e.originalEvent.targetTouches[0].pageX), a.touchMove(b, c), (d = !0));
                    }),
                    a.$slide.on("touchend.lg", function () {
                        a.$outer.hasClass("lg-zoomed") || (d ? ((d = !1), a.touchEnd(c - b)) : a.$el.trigger("onSlideClick.lg"));
                    }));
            }),
            (b.prototype.enableDrag = function () {
                var b = this,
                    c = 0,
                    d = 0,
                    e = !1,
                    f = !1;
                b.s.enableDrag &&
                    b.doCss() &&
                    (b.$slide.on("mousedown.lg", function (d) {
                        b.$outer.hasClass("lg-zoomed") ||
                            b.lgBusy ||
                            a(d.target).text().trim() ||
                            (d.preventDefault(),
                            b.manageSwipeClass(),
                            (c = d.pageX),
                            (e = !0),
                            (b.$outer.scrollLeft += 1),
                            (b.$outer.scrollLeft -= 1),
                            b.$outer.removeClass("lg-grab").addClass("lg-grabbing"),
                            b.$el.trigger("onDragstart.lg"));
                    }),
                    a(window).on("mousemove.lg", function (a) {
                        e && ((f = !0), (d = a.pageX), b.touchMove(c, d), b.$el.trigger("onDragmove.lg"));
                    }),
                    a(window).on("mouseup.lg", function (g) {
                        f ? ((f = !1), b.touchEnd(d - c), b.$el.trigger("onDragend.lg")) : (a(g.target).hasClass("lg-object") || a(g.target).hasClass("lg-video-play")) && b.$el.trigger("onSlideClick.lg"),
                            e && ((e = !1), b.$outer.removeClass("lg-grabbing").addClass("lg-grab"));
                    }));
            }),
            (b.prototype.manageSwipeClass = function () {
                var a = this.index + 1,
                    b = this.index - 1;
                this.s.loop && this.$slide.length > 2 && (0 === this.index ? (b = this.$slide.length - 1) : this.index === this.$slide.length - 1 && (a = 0)),
                    this.$slide.removeClass("lg-next-slide lg-prev-slide"),
                    b > -1 && this.$slide.eq(b).addClass("lg-prev-slide"),
                    this.$slide.eq(a).addClass("lg-next-slide");
            }),
            (b.prototype.mousewheel = function () {
                var a = this;
                a.$outer.on("mousewheel.lg", function (b) {
                    b.deltaY && (b.deltaY > 0 ? a.goToPrevSlide() : a.goToNextSlide(), b.preventDefault());
                });
            }),
            (b.prototype.closeGallery = function () {
                var b = this,
                    c = !1;
                this.$outer.find(".lg-close").on("click.lg", function () {
                    b.destroy();
                }),
                    b.s.closable &&
                        (b.$outer.on("mousedown.lg", function (b) {
                            c = !!(a(b.target).is(".lg-outer") || a(b.target).is(".lg-item ") || a(b.target).is(".lg-img-wrap"));
                        }),
                        b.$outer.on("mousemove.lg", function () {
                            c = !1;
                        }),
                        b.$outer.on("mouseup.lg", function (d) {
                            (a(d.target).is(".lg-outer") || a(d.target).is(".lg-item ") || (a(d.target).is(".lg-img-wrap") && c)) && (b.$outer.hasClass("lg-dragging") || b.destroy());
                        }));
            }),
            (b.prototype.destroy = function (b) {
                var c = this;
                b || (c.$el.trigger("onBeforeClose.lg"), a(window).scrollTop(c.prevScrollTop)),
                    b && (c.s.dynamic || this.$items.off("click.lg click.lgcustom"), a.removeData(c.el, "lightGallery")),
                    this.$el.off(".lg.tm"),
                    a.each(a.fn.lightGallery.modules, function (a) {
                        c.modules[a] && c.modules[a].destroy();
                    }),
                    (this.lGalleryOn = !1),
                    clearTimeout(c.hideBartimeout),
                    (this.hideBartimeout = !1),
                    a(window).off(".lg"),
                    a("body").removeClass("lg-on lg-from-hash"),
                    c.$outer && c.$outer.removeClass("lg-visible"),
                    a(".lg-backdrop").removeClass("in"),
                    setTimeout(function () {
                        c.$outer && c.$outer.remove(), a(".lg-backdrop").remove(), b || c.$el.trigger("onCloseAfter.lg");
                    }, c.s.backdropDuration + 50);
            }),
            (a.fn.lightGallery = function (c) {
                return this.each(function () {
                    if (a.data(this, "lightGallery"))
                        try {
                            a(this).data("lightGallery").init();
                        } catch (a) {
                            console.error("lightGallery has not initiated properly");
                        }
                    else a.data(this, "lightGallery", new b(this, c));
                });
            }),
            (a.fn.lightGallery.modules = {});
    })();
});
!(function (a, b) {
    "function" == typeof define && define.amd
        ? define(["jquery"], function (a) {
              return b(a);
          })
        : "object" == typeof exports
        ? (module.exports = b(require("jquery")))
        : b(jQuery);
})(this, function (a) {
    !(function () {
        "use strict";
        var b = {
                thumbnail: !0,
                animateThumb: !0,
                currentPagerPosition: "middle",
                thumbWidth: 100,
                thumbHeight: "80px",
                thumbContHeight: 100,
                thumbMargin: 5,
                exThumbImage: !1,
                showThumbByDefault: !0,
                toogleThumb: !0,
                pullCaptionUp: !0,
                enableThumbDrag: !0,
                enableThumbSwipe: !0,
                swipeThreshold: 50,
                loadYoutubeThumbnail: !0,
                youtubeThumbSize: 1,
                loadVimeoThumbnail: !0,
                vimeoThumbSize: "thumbnail_small",
                loadDailymotionThumbnail: !0,
            },
            c = function (c) {
                return (
                    (this.core = a(c).data("lightGallery")),
                    (this.core.s = a.extend({}, b, this.core.s)),
                    (this.$el = a(c)),
                    (this.$thumbOuter = null),
                    (this.thumbOuterWidth = 0),
                    (this.thumbTotalWidth = this.core.$items.length * (this.core.s.thumbWidth + this.core.s.thumbMargin)),
                    (this.thumbIndex = this.core.index),
                    this.core.s.animateThumb && (this.core.s.thumbHeight = "100%"),
                    (this.left = 0),
                    this.init(),
                    this
                );
            };
        (c.prototype.init = function () {
            var a = this;
            this.core.s.thumbnail &&
                this.core.$items.length > 1 &&
                (this.core.s.showThumbByDefault &&
                    setTimeout(function () {
                        a.core.$outer.addClass("lg-thumb-open");
                    }, 700),
                this.core.s.pullCaptionUp && this.core.$outer.addClass("lg-pull-caption-up"),
                this.build(),
                this.core.s.animateThumb && this.core.doCss() ? (this.core.s.enableThumbDrag && this.enableThumbDrag(), this.core.s.enableThumbSwipe && this.enableThumbSwipe(), (this.thumbClickable = !1)) : (this.thumbClickable = !0),
                this.toogle(),
                this.thumbkeyPress());
        }),
            (c.prototype.build = function () {
                function b(a, b, c) {
                    var g,
                        h = d.core.isVideo(a, c) || {},
                        i = "";
                    h.youtube || h.vimeo || h.dailymotion
                        ? h.youtube
                            ? (g = d.core.s.loadYoutubeThumbnail ? "//img.youtube.com/vi/" + h.youtube[1] + "/" + d.core.s.youtubeThumbSize + ".jpg" : b)
                            : h.vimeo
                            ? d.core.s.loadVimeoThumbnail
                                ? ((g = "//i.vimeocdn.com/video/error_" + f + ".jpg"), (i = h.vimeo[1]))
                                : (g = b)
                            : h.dailymotion && (g = d.core.s.loadDailymotionThumbnail ? "//www.dailymotion.com/thumbnail/video/" + h.dailymotion[1] : b)
                        : (g = b),
                        (e +=
                            '<div data-vimeo-id="' +
                            i +
                            '" class="lg-thumb-item" style="width:' +
                            d.core.s.thumbWidth +
                            "px; height: " +
                            d.core.s.thumbHeight +
                            "; margin-right: " +
                            d.core.s.thumbMargin +
                            'px"><img src="' +
                            g +
                            '" /></div>'),
                        (i = "");
                }
                var c,
                    d = this,
                    e = "",
                    f = "",
                    g = '<div class="lg-thumb-outer"><div class="lg-thumb lg-group"></div></div>';
                switch (this.core.s.vimeoThumbSize) {
                    case "thumbnail_large":
                        f = "640";
                        break;
                    case "thumbnail_medium":
                        f = "200x150";
                        break;
                    case "thumbnail_small":
                        f = "100x75";
                }
                if (
                    (d.core.$outer.addClass("lg-has-thumb"),
                    d.core.$outer.find(".lg").append(g),
                    (d.$thumbOuter = d.core.$outer.find(".lg-thumb-outer")),
                    (d.thumbOuterWidth = d.$thumbOuter.width()),
                    d.core.s.animateThumb && d.core.$outer.find(".lg-thumb").css({ width: d.thumbTotalWidth + "px", position: "relative" }),
                    this.core.s.animateThumb && d.$thumbOuter.css("height", d.core.s.thumbContHeight + "px"),
                    d.core.s.dynamic)
                )
                    for (var h = 0; h < d.core.s.dynamicEl.length; h++) b(d.core.s.dynamicEl[h].src, d.core.s.dynamicEl[h].thumb, h);
                else
                    d.core.$items.each(function (c) {
                        d.core.s.exThumbImage ? b(a(this).attr("href") || a(this).attr("data-src"), a(this).attr(d.core.s.exThumbImage), c) : b(a(this).attr("href") || a(this).attr("data-src"), a(this).find("img").attr("src"), c);
                    });
                d.core.$outer.find(".lg-thumb").html(e),
                    (c = d.core.$outer.find(".lg-thumb-item")),
                    c.each(function () {
                        var b = a(this),
                            c = b.attr("data-vimeo-id");
                        c &&
                            a.getJSON("//www.vimeo.com/api/v2/video/" + c + ".json?callback=?", { format: "json" }, function (a) {
                                b.find("img").attr("src", a[0][d.core.s.vimeoThumbSize]);
                            });
                    }),
                    c.eq(d.core.index).addClass("active"),
                    d.core.$el.on("onBeforeSlide.lg.tm", function () {
                        c.removeClass("active"), c.eq(d.core.index).addClass("active");
                    }),
                    c.on("click.lg touchend.lg", function () {
                        var b = a(this);
                        setTimeout(function () {
                            ((d.thumbClickable && !d.core.lgBusy) || !d.core.doCss()) && ((d.core.index = b.index()), d.core.slide(d.core.index, !1, !0, !1));
                        }, 50);
                    }),
                    d.core.$el.on("onBeforeSlide.lg.tm", function () {
                        d.animateThumb(d.core.index);
                    }),
                    a(window).on("resize.lg.thumb orientationchange.lg.thumb", function () {
                        setTimeout(function () {
                            d.animateThumb(d.core.index), (d.thumbOuterWidth = d.$thumbOuter.width());
                        }, 200);
                    });
            }),
            (c.prototype.setTranslate = function (a) {
                this.core.$outer.find(".lg-thumb").css({ transform: "translate3d(-" + a + "px, 0px, 0px)" });
            }),
            (c.prototype.animateThumb = function (a) {
                var b = this.core.$outer.find(".lg-thumb");
                if (this.core.s.animateThumb) {
                    var c;
                    switch (this.core.s.currentPagerPosition) {
                        case "left":
                            c = 0;
                            break;
                        case "middle":
                            c = this.thumbOuterWidth / 2 - this.core.s.thumbWidth / 2;
                            break;
                        case "right":
                            c = this.thumbOuterWidth - this.core.s.thumbWidth;
                    }
                    (this.left = (this.core.s.thumbWidth + this.core.s.thumbMargin) * a - 1 - c),
                        this.left > this.thumbTotalWidth - this.thumbOuterWidth && (this.left = this.thumbTotalWidth - this.thumbOuterWidth),
                        this.left < 0 && (this.left = 0),
                        this.core.lGalleryOn
                            ? (b.hasClass("on") || this.core.$outer.find(".lg-thumb").css("transition-duration", this.core.s.speed + "ms"), this.core.doCss() || b.animate({ left: -this.left + "px" }, this.core.s.speed))
                            : this.core.doCss() || b.css("left", -this.left + "px"),
                        this.setTranslate(this.left);
                }
            }),
            (c.prototype.enableThumbDrag = function () {
                var b = this,
                    c = 0,
                    d = 0,
                    e = !1,
                    f = !1,
                    g = 0;
                b.$thumbOuter.addClass("lg-grab"),
                    b.core.$outer.find(".lg-thumb").on("mousedown.lg.thumb", function (a) {
                        b.thumbTotalWidth > b.thumbOuterWidth &&
                            (a.preventDefault(), (c = a.pageX), (e = !0), (b.core.$outer.scrollLeft += 1), (b.core.$outer.scrollLeft -= 1), (b.thumbClickable = !1), b.$thumbOuter.removeClass("lg-grab").addClass("lg-grabbing"));
                    }),
                    a(window).on("mousemove.lg.thumb", function (a) {
                        e &&
                            ((g = b.left),
                            (f = !0),
                            (d = a.pageX),
                            b.$thumbOuter.addClass("lg-dragging"),
                            (g -= d - c),
                            g > b.thumbTotalWidth - b.thumbOuterWidth && (g = b.thumbTotalWidth - b.thumbOuterWidth),
                            g < 0 && (g = 0),
                            b.setTranslate(g));
                    }),
                    a(window).on("mouseup.lg.thumb", function () {
                        f ? ((f = !1), b.$thumbOuter.removeClass("lg-dragging"), (b.left = g), Math.abs(d - c) < b.core.s.swipeThreshold && (b.thumbClickable = !0)) : (b.thumbClickable = !0),
                            e && ((e = !1), b.$thumbOuter.removeClass("lg-grabbing").addClass("lg-grab"));
                    });
            }),
            (c.prototype.enableThumbSwipe = function () {
                var a = this,
                    b = 0,
                    c = 0,
                    d = !1,
                    e = 0;
                a.core.$outer.find(".lg-thumb").on("touchstart.lg", function (c) {
                    a.thumbTotalWidth > a.thumbOuterWidth && (c.preventDefault(), (b = c.originalEvent.targetTouches[0].pageX), (a.thumbClickable = !1));
                }),
                    a.core.$outer.find(".lg-thumb").on("touchmove.lg", function (f) {
                        a.thumbTotalWidth > a.thumbOuterWidth &&
                            (f.preventDefault(),
                            (c = f.originalEvent.targetTouches[0].pageX),
                            (d = !0),
                            a.$thumbOuter.addClass("lg-dragging"),
                            (e = a.left),
                            (e -= c - b),
                            e > a.thumbTotalWidth - a.thumbOuterWidth && (e = a.thumbTotalWidth - a.thumbOuterWidth),
                            e < 0 && (e = 0),
                            a.setTranslate(e));
                    }),
                    a.core.$outer.find(".lg-thumb").on("touchend.lg", function () {
                        a.thumbTotalWidth > a.thumbOuterWidth && d ? ((d = !1), a.$thumbOuter.removeClass("lg-dragging"), Math.abs(c - b) < a.core.s.swipeThreshold && (a.thumbClickable = !0), (a.left = e)) : (a.thumbClickable = !0);
                    });
            }),
            (c.prototype.toogle = function () {
                var a = this;
                a.core.s.toogleThumb &&
                    (a.core.$outer.addClass("lg-can-toggle"),
                    a.$thumbOuter.append('<span class="lg-toogle-thumb lg-icon"></span>'),
                    a.core.$outer.find(".lg-toogle-thumb").on("click.lg", function () {
                        a.core.$outer.toggleClass("lg-thumb-open");
                    }));
            }),
            (c.prototype.thumbkeyPress = function () {
                var b = this;
                a(window).on("keydown.lg.thumb", function (a) {
                    38 === a.keyCode ? (a.preventDefault(), b.core.$outer.addClass("lg-thumb-open")) : 40 === a.keyCode && (a.preventDefault(), b.core.$outer.removeClass("lg-thumb-open"));
                });
            }),
            (c.prototype.destroy = function () {
                this.core.s.thumbnail && this.core.$items.length > 1 && (a(window).off("resize.lg.thumb orientationchange.lg.thumb keydown.lg.thumb"), this.$thumbOuter.remove(), this.core.$outer.removeClass("lg-has-thumb"));
            }),
            (a.fn.lightGallery.modules.Thumbnail = c);
    })();
});
!(function (a, b) {
    "function" == typeof define && define.amd
        ? define(["jquery"], function (a) {
              return b(a);
          })
        : "object" == typeof exports
        ? (module.exports = b(require("jquery")))
        : b(jQuery);
})(this, function (a) {
    !(function () {
        "use strict";
        var b = { fullScreen: !0 },
            c = function (c) {
                return (this.core = a(c).data("lightGallery")), (this.$el = a(c)), (this.core.s = a.extend({}, b, this.core.s)), this.init(), this;
            };
        (c.prototype.init = function () {
            var a = "";
            if (this.core.s.fullScreen) {
                if (!(document.fullscreenEnabled || document.webkitFullscreenEnabled || document.mozFullScreenEnabled || document.msFullscreenEnabled)) return;
                (a = '<span class="lg-fullscreen lg-icon"></span>'), this.core.$outer.find(".lg-toolbar").append(a), this.fullScreen();
            }
        }),
            (c.prototype.requestFullscreen = function () {
                var a = document.documentElement;
                a.requestFullscreen ? a.requestFullscreen() : a.msRequestFullscreen ? a.msRequestFullscreen() : a.mozRequestFullScreen ? a.mozRequestFullScreen() : a.webkitRequestFullscreen && a.webkitRequestFullscreen();
            }),
            (c.prototype.exitFullscreen = function () {
                document.fullscreenElement
                    ? document.fullscreenElement()
                    : document.msExitFullscreen
                    ? document.msExitFullscreen()
                    : document.mozCancelFullScreen
                    ? document.mozCancelFullScreen()
                    : document.webkitExitFullscreen && document.webkitExitFullscreen();
            }),
            (c.prototype.fullScreen = function () {
                var b = this;
                a(document).on("fullscreenchange.lg webkitfullscreenchange.lg mozfullscreenchange.lg MSFullscreenChange.lg", function () {
                    b.core.$outer.toggleClass("lg-fullscreen-on");
                }),
                    this.core.$outer.find(".lg-fullscreen").on("click.lg", function () {
                        document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.msFullscreenElement ? b.exitFullscreen() : b.requestFullscreen();
                    });
            }),
            (c.prototype.destroy = function () {
                this.exitFullscreen(), a(document).off("fullscreenchange.lg webkitfullscreenchange.lg mozfullscreenchange.lg MSFullscreenChange.lg");
            }),
            (a.fn.lightGallery.modules.fullscreen = c);
    })();
});
!(function (a, b) {
    "function" == typeof define && define.amd
        ? define(["jquery"], function (a) {
              return b(a);
          })
        : "object" == typeof exports
        ? (module.exports = b(require("jquery")))
        : b(jQuery);
})(this, function (a) {
    !(function () {
        "use strict";
        var b = { autoplay: !1, pause: 5e3, progressBar: !0, fourceAutoplay: !1, autoplayControls: !0, appendAutoplayControlsTo: ".lg-toolbar" },
            c = function (c) {
                return (
                    (this.core = a(c).data("lightGallery")),
                    (this.$el = a(c)),
                    !(this.core.$items.length < 2) &&
                        ((this.core.s = a.extend({}, b, this.core.s)),
                        (this.interval = !1),
                        (this.fromAuto = !0),
                        (this.canceledOnTouch = !1),
                        (this.fourceAutoplayTemp = this.core.s.fourceAutoplay),
                        this.core.doCss() || (this.core.s.progressBar = !1),
                        this.init(),
                        this)
                );
            };
        (c.prototype.init = function () {
            var a = this;
            a.core.s.autoplayControls && a.controls(),
                a.core.s.progressBar && a.core.$outer.find(".lg").append('<div class="lg-progress-bar"><div class="lg-progress"></div></div>'),
                a.progress(),
                a.core.s.autoplay &&
                    a.$el.one("onSlideItemLoad.lg.tm", function () {
                        a.startlAuto();
                    }),
                a.$el.on("onDragstart.lg.tm touchstart.lg.tm", function () {
                    a.interval && (a.cancelAuto(), (a.canceledOnTouch = !0));
                }),
                a.$el.on("onDragend.lg.tm touchend.lg.tm onSlideClick.lg.tm", function () {
                    !a.interval && a.canceledOnTouch && (a.startlAuto(), (a.canceledOnTouch = !1));
                });
        }),
            (c.prototype.progress = function () {
                var a,
                    b,
                    c = this;
                c.$el.on("onBeforeSlide.lg.tm", function () {
                    c.core.s.progressBar &&
                        c.fromAuto &&
                        ((a = c.core.$outer.find(".lg-progress-bar")),
                        (b = c.core.$outer.find(".lg-progress")),
                        c.interval &&
                            (b.removeAttr("style"),
                            a.removeClass("lg-start"),
                            setTimeout(function () {
                                b.css("transition", "width " + (c.core.s.speed + c.core.s.pause) + "ms ease 0s"), a.addClass("lg-start");
                            }, 20))),
                        c.fromAuto || c.core.s.fourceAutoplay || c.cancelAuto(),
                        (c.fromAuto = !1);
                });
            }),
            (c.prototype.controls = function () {
                var b = this,
                    c = '<span class="lg-autoplay-button lg-icon"></span>';
                a(this.core.s.appendAutoplayControlsTo).append(c),
                    b.core.$outer.find(".lg-autoplay-button").on("click.lg", function () {
                        a(b.core.$outer).hasClass("lg-show-autoplay") ? (b.cancelAuto(), (b.core.s.fourceAutoplay = !1)) : b.interval || (b.startlAuto(), (b.core.s.fourceAutoplay = b.fourceAutoplayTemp));
                    });
            }),
            (c.prototype.startlAuto = function () {
                var a = this;
                a.core.$outer.find(".lg-progress").css("transition", "width " + (a.core.s.speed + a.core.s.pause) + "ms ease 0s"),
                    a.core.$outer.addClass("lg-show-autoplay"),
                    a.core.$outer.find(".lg-progress-bar").addClass("lg-start"),
                    (a.interval = setInterval(function () {
                        a.core.index + 1 < a.core.$items.length ? a.core.index++ : (a.core.index = 0), (a.fromAuto = !0), a.core.slide(a.core.index, !1, !1, "next");
                    }, a.core.s.speed + a.core.s.pause));
            }),
            (c.prototype.cancelAuto = function () {
                clearInterval(this.interval),
                    (this.interval = !1),
                    this.core.$outer.find(".lg-progress").removeAttr("style"),
                    this.core.$outer.removeClass("lg-show-autoplay"),
                    this.core.$outer.find(".lg-progress-bar").removeClass("lg-start");
            }),
            (c.prototype.destroy = function () {
                this.cancelAuto(), this.core.$outer.find(".lg-progress-bar").remove();
            }),
            (a.fn.lightGallery.modules.autoplay = c);
    })();
});
!(function (a, b) {
    "function" == typeof define && define.amd
        ? define(["jquery"], function (a) {
              return b(a);
          })
        : "object" == typeof exports
        ? (module.exports = b(require("jquery")))
        : b(jQuery);
})(this, function (a) {
    !(function () {
        "use strict";
        var b = function () {
                var a = !1,
                    b = navigator.userAgent.match(/Chrom(e|ium)\/([0-9]+)\./);
                return b && parseInt(b[2], 10) < 54 && (a = !0), a;
            },
            c = { scale: 1, zoom: !0, actualSize: !0, enableZoomAfter: 300, useLeftForZoom: b() },
            d = function (b) {
                return (
                    (this.core = a(b).data("lightGallery")),
                    (this.core.s = a.extend({}, c, this.core.s)),
                    this.core.s.zoom && this.core.doCss() && (this.init(), (this.zoomabletimeout = !1), (this.pageX = a(window).width() / 2), (this.pageY = a(window).height() / 2 + a(window).scrollTop())),
                    this
                );
            };
        (d.prototype.init = function () {
            var b = this,
                c = '<span id="lg-zoom-in" class="lg-icon"></span><span id="lg-zoom-out" class="lg-icon"></span>';
            b.core.s.actualSize && (c += '<span id="lg-actual-size" class="lg-icon"></span>'),
                b.core.s.useLeftForZoom ? b.core.$outer.addClass("lg-use-left-for-zoom") : b.core.$outer.addClass("lg-use-transition-for-zoom"),
                this.core.$outer.find(".lg-toolbar").append(c),
                b.core.$el.on("onSlideItemLoad.lg.tm.zoom", function (c, d, e) {
                    var f = b.core.s.enableZoomAfter + e;
                    a("body").hasClass("lg-from-hash") && e ? (f = 0) : a("body").removeClass("lg-from-hash"),
                        (b.zoomabletimeout = setTimeout(function () {
                            b.core.$slide.eq(d).addClass("lg-zoomable");
                        }, f + 30));
                });
            var d = 1,
                e = function (c) {
                    var d,
                        e,
                        f = b.core.$outer.find(".lg-current .lg-image"),
                        g = (a(window).width() - f.prop("offsetWidth")) / 2,
                        h = (a(window).height() - f.prop("offsetHeight")) / 2 + a(window).scrollTop();
                    (d = b.pageX - g), (e = b.pageY - h);
                    var i = (c - 1) * d,
                        j = (c - 1) * e;
                    f.css("transform", "scale3d(" + c + ", " + c + ", 1)").attr("data-scale", c),
                        b.core.s.useLeftForZoom
                            ? f
                                  .parent()
                                  .css({ left: -i + "px", top: -j + "px" })
                                  .attr("data-x", i)
                                  .attr("data-y", j)
                            : f
                                  .parent()
                                  .css("transform", "translate3d(-" + i + "px, -" + j + "px, 0)")
                                  .attr("data-x", i)
                                  .attr("data-y", j);
                },
                f = function () {
                    d > 1 ? b.core.$outer.addClass("lg-zoomed") : b.resetZoom(), d < 1 && (d = 1), e(d);
                },
                g = function (c, e, g, h) {
                    var i,
                        j = e.prop("offsetWidth");
                    i = b.core.s.dynamic ? b.core.s.dynamicEl[g].width || e[0].naturalWidth || j : b.core.$items.eq(g).attr("data-width") || e[0].naturalWidth || j;
                    var k;
                    b.core.$outer.hasClass("lg-zoomed") ? (d = 1) : i > j && ((k = i / j), (d = k || 2)),
                        h
                            ? ((b.pageX = a(window).width() / 2), (b.pageY = a(window).height() / 2 + a(window).scrollTop()))
                            : ((b.pageX = c.pageX || c.originalEvent.targetTouches[0].pageX), (b.pageY = c.pageY || c.originalEvent.targetTouches[0].pageY)),
                        f(),
                        setTimeout(function () {
                            b.core.$outer.removeClass("lg-grabbing").addClass("lg-grab");
                        }, 10);
                },
                h = !1;
            b.core.$el.on("onAferAppendSlide.lg.tm.zoom", function (a, c) {
                var d = b.core.$slide.eq(c).find(".lg-image");
                d.on("dblclick", function (a) {
                    g(a, d, c);
                }),
                    d.on("touchstart", function (a) {
                        h
                            ? (clearTimeout(h), (h = null), g(a, d, c))
                            : (h = setTimeout(function () {
                                  h = null;
                              }, 300)),
                            a.preventDefault();
                    });
            }),
                a(window).on("resize.lg.zoom scroll.lg.zoom orientationchange.lg.zoom", function () {
                    (b.pageX = a(window).width() / 2), (b.pageY = a(window).height() / 2 + a(window).scrollTop()), e(d);
                }),
                a("#lg-zoom-out").on("click.lg", function () {
                    b.core.$outer.find(".lg-current .lg-image").length && ((d -= b.core.s.scale), f());
                }),
                a("#lg-zoom-in").on("click.lg", function () {
                    b.core.$outer.find(".lg-current .lg-image").length && ((d += b.core.s.scale), f());
                }),
                a("#lg-actual-size").on("click.lg", function (a) {
                    g(a, b.core.$slide.eq(b.core.index).find(".lg-image"), b.core.index, !0);
                }),
                b.core.$el.on("onBeforeSlide.lg.tm", function () {
                    (d = 1), b.resetZoom();
                }),
                b.zoomDrag(),
                b.zoomSwipe();
        }),
            (d.prototype.resetZoom = function () {
                this.core.$outer.removeClass("lg-zoomed"),
                    this.core.$slide.find(".lg-img-wrap").removeAttr("style data-x data-y"),
                    this.core.$slide.find(".lg-image").removeAttr("style data-scale"),
                    (this.pageX = a(window).width() / 2),
                    (this.pageY = a(window).height() / 2 + a(window).scrollTop());
            }),
            (d.prototype.zoomSwipe = function () {
                var a = this,
                    b = {},
                    c = {},
                    d = !1,
                    e = !1,
                    f = !1;
                a.core.$slide.on("touchstart.lg", function (c) {
                    if (a.core.$outer.hasClass("lg-zoomed")) {
                        var d = a.core.$slide.eq(a.core.index).find(".lg-object");
                        (f = d.prop("offsetHeight") * d.attr("data-scale") > a.core.$outer.find(".lg").height()),
                            (e = d.prop("offsetWidth") * d.attr("data-scale") > a.core.$outer.find(".lg").width()),
                            (e || f) && (c.preventDefault(), (b = { x: c.originalEvent.targetTouches[0].pageX, y: c.originalEvent.targetTouches[0].pageY }));
                    }
                }),
                    a.core.$slide.on("touchmove.lg", function (g) {
                        if (a.core.$outer.hasClass("lg-zoomed")) {
                            var h,
                                i,
                                j = a.core.$slide.eq(a.core.index).find(".lg-img-wrap");
                            g.preventDefault(),
                                (d = !0),
                                (c = { x: g.originalEvent.targetTouches[0].pageX, y: g.originalEvent.targetTouches[0].pageY }),
                                a.core.$outer.addClass("lg-zoom-dragging"),
                                (i = f ? -Math.abs(j.attr("data-y")) + (c.y - b.y) : -Math.abs(j.attr("data-y"))),
                                (h = e ? -Math.abs(j.attr("data-x")) + (c.x - b.x) : -Math.abs(j.attr("data-x"))),
                                (Math.abs(c.x - b.x) > 15 || Math.abs(c.y - b.y) > 15) && (a.core.s.useLeftForZoom ? j.css({ left: h + "px", top: i + "px" }) : j.css("transform", "translate3d(" + h + "px, " + i + "px, 0)"));
                        }
                    }),
                    a.core.$slide.on("touchend.lg", function () {
                        a.core.$outer.hasClass("lg-zoomed") && d && ((d = !1), a.core.$outer.removeClass("lg-zoom-dragging"), a.touchendZoom(b, c, e, f));
                    });
            }),
            (d.prototype.zoomDrag = function () {
                var b = this,
                    c = {},
                    d = {},
                    e = !1,
                    f = !1,
                    g = !1,
                    h = !1;
                b.core.$slide.on("mousedown.lg.zoom", function (d) {
                    var f = b.core.$slide.eq(b.core.index).find(".lg-object");
                    (h = f.prop("offsetHeight") * f.attr("data-scale") > b.core.$outer.find(".lg").height()),
                        (g = f.prop("offsetWidth") * f.attr("data-scale") > b.core.$outer.find(".lg").width()),
                        b.core.$outer.hasClass("lg-zoomed") &&
                            a(d.target).hasClass("lg-object") &&
                            (g || h) &&
                            (d.preventDefault(), (c = { x: d.pageX, y: d.pageY }), (e = !0), (b.core.$outer.scrollLeft += 1), (b.core.$outer.scrollLeft -= 1), b.core.$outer.removeClass("lg-grab").addClass("lg-grabbing"));
                }),
                    a(window).on("mousemove.lg.zoom", function (a) {
                        if (e) {
                            var i,
                                j,
                                k = b.core.$slide.eq(b.core.index).find(".lg-img-wrap");
                            (f = !0),
                                (d = { x: a.pageX, y: a.pageY }),
                                b.core.$outer.addClass("lg-zoom-dragging"),
                                (j = h ? -Math.abs(k.attr("data-y")) + (d.y - c.y) : -Math.abs(k.attr("data-y"))),
                                (i = g ? -Math.abs(k.attr("data-x")) + (d.x - c.x) : -Math.abs(k.attr("data-x"))),
                                b.core.s.useLeftForZoom ? k.css({ left: i + "px", top: j + "px" }) : k.css("transform", "translate3d(" + i + "px, " + j + "px, 0)");
                        }
                    }),
                    a(window).on("mouseup.lg.zoom", function (a) {
                        e && ((e = !1), b.core.$outer.removeClass("lg-zoom-dragging"), !f || (c.x === d.x && c.y === d.y) || ((d = { x: a.pageX, y: a.pageY }), b.touchendZoom(c, d, g, h)), (f = !1)),
                            b.core.$outer.removeClass("lg-grabbing").addClass("lg-grab");
                    });
            }),
            (d.prototype.touchendZoom = function (a, b, c, d) {
                var e = this,
                    f = e.core.$slide.eq(e.core.index).find(".lg-img-wrap"),
                    g = e.core.$slide.eq(e.core.index).find(".lg-object"),
                    h = -Math.abs(f.attr("data-x")) + (b.x - a.x),
                    i = -Math.abs(f.attr("data-y")) + (b.y - a.y),
                    j = (e.core.$outer.find(".lg").height() - g.prop("offsetHeight")) / 2,
                    k = Math.abs(g.prop("offsetHeight") * Math.abs(g.attr("data-scale")) - e.core.$outer.find(".lg").height() + j),
                    l = (e.core.$outer.find(".lg").width() - g.prop("offsetWidth")) / 2,
                    m = Math.abs(g.prop("offsetWidth") * Math.abs(g.attr("data-scale")) - e.core.$outer.find(".lg").width() + l);
                (Math.abs(b.x - a.x) > 15 || Math.abs(b.y - a.y) > 15) &&
                    (d && (i <= -k ? (i = -k) : i >= -j && (i = -j)),
                    c && (h <= -m ? (h = -m) : h >= -l && (h = -l)),
                    d ? f.attr("data-y", Math.abs(i)) : (i = -Math.abs(f.attr("data-y"))),
                    c ? f.attr("data-x", Math.abs(h)) : (h = -Math.abs(f.attr("data-x"))),
                    e.core.s.useLeftForZoom ? f.css({ left: h + "px", top: i + "px" }) : f.css("transform", "translate3d(" + h + "px, " + i + "px, 0)"));
            }),
            (d.prototype.destroy = function () {
                var b = this;
                b.core.$el.off(".lg.zoom"), a(window).off(".lg.zoom"), b.core.$slide.off(".lg.zoom"), b.core.$el.off(".lg.tm.zoom"), b.resetZoom(), clearTimeout(b.zoomabletimeout), (b.zoomabletimeout = !1);
            }),
            (a.fn.lightGallery.modules.zoom = d);
    })();
});
/*! jQuery Validation Plugin - v1.15.0 - 2/24/2016
 * http://jqueryvalidation.org/
 * Copyright (c) 2016 JÃ¶rn Zaefferer; Licensed MIT */
!(function (a) {
    "function" == typeof define && define.amd ? define(["jquery"], a) : "object" == typeof module && module.exports ? (module.exports = a(require("jquery"))) : a(jQuery);
})(function (a) {
    a.extend(a.fn, {
        validate: function (b) {
            if (!this.length) return void (b && b.debug && window.console && console.warn("Nothing selected, can't validate, returning nothing."));
            var c = a.data(this[0], "validator");
            return c
                ? c
                : (this.attr("novalidate", "novalidate"),
                  (c = new a.validator(b, this[0])),
                  a.data(this[0], "validator", c),
                  c.settings.onsubmit &&
                      (this.on("click.validate", ":submit", function (b) {
                          c.settings.submitHandler && (c.submitButton = b.target), a(this).hasClass("cancel") && (c.cancelSubmit = !0), void 0 !== a(this).attr("formnovalidate") && (c.cancelSubmit = !0);
                      }),
                      this.on("submit.validate", function (b) {
                          function d() {
                              var d, e;
                              return c.settings.submitHandler
                                  ? (c.submitButton && (d = a("<input type='hidden'/>").attr("name", c.submitButton.name).val(a(c.submitButton).val()).appendTo(c.currentForm)),
                                    (e = c.settings.submitHandler.call(c, c.currentForm, b)),
                                    c.submitButton && d.remove(),
                                    void 0 !== e ? e : !1)
                                  : !0;
                          }
                          return c.settings.debug && b.preventDefault(), c.cancelSubmit ? ((c.cancelSubmit = !1), d()) : c.form() ? (c.pendingRequest ? ((c.formSubmitted = !0), !1) : d()) : (c.focusInvalid(), !1);
                      })),
                  c);
        },
        valid: function () {
            var b, c, d;
            return (
                a(this[0]).is("form")
                    ? (b = this.validate().form())
                    : ((d = []),
                      (b = !0),
                      (c = a(this[0].form).validate()),
                      this.each(function () {
                          (b = c.element(this) && b), b || (d = d.concat(c.errorList));
                      }),
                      (c.errorList = d)),
                b
            );
        },
        rules: function (b, c) {
            if (this.length) {
                var d,
                    e,
                    f,
                    g,
                    h,
                    i,
                    j = this[0];
                if (b)
                    switch (((d = a.data(j.form, "validator").settings), (e = d.rules), (f = a.validator.staticRules(j)), b)) {
                        case "add":
                            a.extend(f, a.validator.normalizeRule(c)), delete f.messages, (e[j.name] = f), c.messages && (d.messages[j.name] = a.extend(d.messages[j.name], c.messages));
                            break;
                        case "remove":
                            return c
                                ? ((i = {}),
                                  a.each(c.split(/\s/), function (b, c) {
                                      (i[c] = f[c]), delete f[c], "required" === c && a(j).removeAttr("aria-required");
                                  }),
                                  i)
                                : (delete e[j.name], f);
                    }
                return (
                    (g = a.validator.normalizeRules(a.extend({}, a.validator.classRules(j), a.validator.attributeRules(j), a.validator.dataRules(j), a.validator.staticRules(j)), j)),
                    g.required && ((h = g.required), delete g.required, (g = a.extend({ required: h }, g)), a(j).attr("aria-required", "true")),
                    g.remote && ((h = g.remote), delete g.remote, (g = a.extend(g, { remote: h }))),
                    g
                );
            }
        },
    }),
        a.extend(a.expr[":"], {
            blank: function (b) {
                return !a.trim("" + a(b).val());
            },
            filled: function (b) {
                var c = a(b).val();
                return null !== c && !!a.trim("" + c);
            },
            unchecked: function (b) {
                return !a(b).prop("checked");
            },
        }),
        (a.validator = function (b, c) {
            (this.settings = a.extend(!0, {}, a.validator.defaults, b)), (this.currentForm = c), this.init();
        }),
        (a.validator.format = function (b, c) {
            return 1 === arguments.length
                ? function () {
                      var c = a.makeArray(arguments);
                      return c.unshift(b), a.validator.format.apply(this, c);
                  }
                : void 0 === c
                ? b
                : (arguments.length > 2 && c.constructor !== Array && (c = a.makeArray(arguments).slice(1)),
                  c.constructor !== Array && (c = [c]),
                  a.each(c, function (a, c) {
                      b = b.replace(new RegExp("\\{" + a + "\\}", "g"), function () {
                          return c;
                      });
                  }),
                  b);
        }),
        a.extend(a.validator, {
            defaults: {
                messages: {},
                groups: {},
                rules: {},
                errorClass: "error",
                pendingClass: "pending",
                validClass: "valid",
                errorElement: "label",
                focusCleanup: !1,
                focusInvalid: !0,
                errorContainer: a([]),
                errorLabelContainer: a([]),
                onsubmit: !0,
                ignore: ":hidden",
                ignoreTitle: !1,
                onfocusin: function (a) {
                    (this.lastActive = a), this.settings.focusCleanup && (this.settings.unhighlight && this.settings.unhighlight.call(this, a, this.settings.errorClass, this.settings.validClass), this.hideThese(this.errorsFor(a)));
                },
                onfocusout: function (a) {
                    this.checkable(a) || (!(a.name in this.submitted) && this.optional(a)) || this.element(a);
                },
                onkeyup: function (b, c) {
                    var d = [16, 17, 18, 20, 35, 36, 37, 38, 39, 40, 45, 144, 225];
                    (9 === c.which && "" === this.elementValue(b)) || -1 !== a.inArray(c.keyCode, d) || ((b.name in this.submitted || b.name in this.invalid) && this.element(b));
                },
                onclick: function (a) {
                    a.name in this.submitted ? this.element(a) : a.parentNode.name in this.submitted && this.element(a.parentNode);
                },
                highlight: function (b, c, d) {
                    "radio" === b.type ? this.findByName(b.name).addClass(c).removeClass(d) : a(b).addClass(c).removeClass(d);
                },
                unhighlight: function (b, c, d) {
                    "radio" === b.type ? this.findByName(b.name).removeClass(c).addClass(d) : a(b).removeClass(c).addClass(d);
                },
            },
            setDefaults: function (b) {
                a.extend(a.validator.defaults, b);
            },
            messages: {
                required: "This field is required.",
                remote: "Please fix this field.",
                email: "Please enter a valid email address.",
                url: "Please enter a valid URL.",
                date: "Please enter a valid date.",
                dateISO: "Please enter a valid date ( ISO ).",
                number: "Please enter a valid number.",
                digits: "Please enter only digits.",
                equalTo: "Please enter the same value again.",
                maxlength: a.validator.format("Please enter no more than {0} characters."),
                minlength: a.validator.format("Please enter at least {0} characters."),
                rangelength: a.validator.format("Please enter a value between {0} and {1} characters long."),
                range: a.validator.format("Please enter a value between {0} and {1}."),
                max: a.validator.format("Please enter a value less than or equal to {0}."),
                min: a.validator.format("Please enter a value greater than or equal to {0}."),
                step: a.validator.format("Please enter a multiple of {0}."),
            },
            autoCreateRanges: !1,
            prototype: {
                init: function () {
                    function b(b) {
                        var c = a.data(this.form, "validator"),
                            d = "on" + b.type.replace(/^validate/, ""),
                            e = c.settings;
                        e[d] && !a(this).is(e.ignore) && e[d].call(c, this, b);
                    }
                    (this.labelContainer = a(this.settings.errorLabelContainer)),
                        (this.errorContext = (this.labelContainer.length && this.labelContainer) || a(this.currentForm)),
                        (this.containers = a(this.settings.errorContainer).add(this.settings.errorLabelContainer)),
                        (this.submitted = {}),
                        (this.valueCache = {}),
                        (this.pendingRequest = 0),
                        (this.pending = {}),
                        (this.invalid = {}),
                        this.reset();
                    var c,
                        d = (this.groups = {});
                    a.each(this.settings.groups, function (b, c) {
                        "string" == typeof c && (c = c.split(/\s/)),
                            a.each(c, function (a, c) {
                                d[c] = b;
                            });
                    }),
                        (c = this.settings.rules),
                        a.each(c, function (b, d) {
                            c[b] = a.validator.normalizeRule(d);
                        }),
                        a(this.currentForm)
                            .on(
                                "focusin.validate focusout.validate keyup.validate",
                                ":text, [type='password'], [type='file'], select, textarea, [type='number'], [type='search'], [type='tel'], [type='url'], [type='email'], [type='datetime'], [type='date'], [type='month'], [type='week'], [type='time'], [type='datetime-local'], [type='range'], [type='color'], [type='radio'], [type='checkbox'], [contenteditable]",
                                b
                            )
                            .on("click.validate", "select, option, [type='radio'], [type='checkbox']", b),
                        this.settings.invalidHandler && a(this.currentForm).on("invalid-form.validate", this.settings.invalidHandler),
                        a(this.currentForm).find("[required], [data-rule-required], .required").attr("aria-required", "true");
                },
                form: function () {
                    return this.checkForm(), a.extend(this.submitted, this.errorMap), (this.invalid = a.extend({}, this.errorMap)), this.valid() || a(this.currentForm).triggerHandler("invalid-form", [this]), this.showErrors(), this.valid();
                },
                checkForm: function () {
                    this.prepareForm();
                    for (var a = 0, b = (this.currentElements = this.elements()); b[a]; a++) this.check(b[a]);
                    return this.valid();
                },
                element: function (b) {
                    var c,
                        d,
                        e = this.clean(b),
                        f = this.validationTargetFor(e),
                        g = this,
                        h = !0;
                    return (
                        void 0 === f
                            ? delete this.invalid[e.name]
                            : (this.prepareElement(f),
                              (this.currentElements = a(f)),
                              (d = this.groups[f.name]),
                              d &&
                                  a.each(this.groups, function (a, b) {
                                      b === d && a !== f.name && ((e = g.validationTargetFor(g.clean(g.findByName(a)))), e && e.name in g.invalid && (g.currentElements.push(e), (h = h && g.check(e))));
                                  }),
                              (c = this.check(f) !== !1),
                              (h = h && c),
                              c ? (this.invalid[f.name] = !1) : (this.invalid[f.name] = !0),
                              this.numberOfInvalids() || (this.toHide = this.toHide.add(this.containers)),
                              this.showErrors(),
                              a(b).attr("aria-invalid", !c)),
                        h
                    );
                },
                showErrors: function (b) {
                    if (b) {
                        var c = this;
                        a.extend(this.errorMap, b),
                            (this.errorList = a.map(this.errorMap, function (a, b) {
                                return { message: a, element: c.findByName(b)[0] };
                            })),
                            (this.successList = a.grep(this.successList, function (a) {
                                return !(a.name in b);
                            }));
                    }
                    this.settings.showErrors ? this.settings.showErrors.call(this, this.errorMap, this.errorList) : this.defaultShowErrors();
                },
                resetForm: function () {
                    a.fn.resetForm && a(this.currentForm).resetForm(), (this.invalid = {}), (this.submitted = {}), this.prepareForm(), this.hideErrors();
                    var b = this.elements().removeData("previousValue").removeAttr("aria-invalid");
                    this.resetElements(b);
                },
                resetElements: function (a) {
                    var b;
                    if (this.settings.unhighlight) for (b = 0; a[b]; b++) this.settings.unhighlight.call(this, a[b], this.settings.errorClass, ""), this.findByName(a[b].name).removeClass(this.settings.validClass);
                    else a.removeClass(this.settings.errorClass).removeClass(this.settings.validClass);
                },
                numberOfInvalids: function () {
                    return this.objectLength(this.invalid);
                },
                objectLength: function (a) {
                    var b,
                        c = 0;
                    for (b in a) a[b] && c++;
                    return c;
                },
                hideErrors: function () {
                    this.hideThese(this.toHide);
                },
                hideThese: function (a) {
                    a.not(this.containers).text(""), this.addWrapper(a).hide();
                },
                valid: function () {
                    return 0 === this.size();
                },
                size: function () {
                    return this.errorList.length;
                },
                focusInvalid: function () {
                    if (this.settings.focusInvalid)
                        try {
                            a(this.findLastActive() || (this.errorList.length && this.errorList[0].element) || [])
                                .filter(":visible")
                                .focus()
                                .trigger("focusin");
                        } catch (b) {}
                },
                findLastActive: function () {
                    var b = this.lastActive;
                    return (
                        b &&
                        1 ===
                            a.grep(this.errorList, function (a) {
                                return a.element.name === b.name;
                            }).length &&
                        b
                    );
                },
                elements: function () {
                    var b = this,
                        c = {};
                    return a(this.currentForm)
                        .find("input, select, textarea, [contenteditable]")
                        .not(":submit, :reset, :image, :disabled")
                        .not(this.settings.ignore)
                        .filter(function () {
                            var d = this.name || a(this).attr("name");
                            return (
                                !d && b.settings.debug && window.console && console.error("%o has no name assigned", this),
                                this.hasAttribute("contenteditable") && (this.form = a(this).closest("form")[0]),
                                d in c || !b.objectLength(a(this).rules()) ? !1 : ((c[d] = !0), !0)
                            );
                        });
                },
                clean: function (b) {
                    return a(b)[0];
                },
                errors: function () {
                    var b = this.settings.errorClass.split(" ").join(".");
                    return a(this.settings.errorElement + "." + b, this.errorContext);
                },
                resetInternals: function () {
                    (this.successList = []), (this.errorList = []), (this.errorMap = {}), (this.toShow = a([])), (this.toHide = a([]));
                },
                reset: function () {
                    this.resetInternals(), (this.currentElements = a([]));
                },
                prepareForm: function () {
                    this.reset(), (this.toHide = this.errors().add(this.containers));
                },
                prepareElement: function (a) {
                    this.reset(), (this.toHide = this.errorsFor(a));
                },
                elementValue: function (b) {
                    var c,
                        d,
                        e = a(b),
                        f = b.type;
                    return "radio" === f || "checkbox" === f
                        ? this.findByName(b.name).filter(":checked").val()
                        : "number" === f && "undefined" != typeof b.validity
                        ? b.validity.badInput
                            ? "NaN"
                            : e.val()
                        : ((c = b.hasAttribute("contenteditable") ? e.text() : e.val()),
                          "file" === f
                              ? "C:\\fakepath\\" === c.substr(0, 12)
                                  ? c.substr(12)
                                  : ((d = c.lastIndexOf("/")), d >= 0 ? c.substr(d + 1) : ((d = c.lastIndexOf("\\")), d >= 0 ? c.substr(d + 1) : c))
                              : "string" == typeof c
                              ? c.replace(/\r/g, "")
                              : c);
                },
                check: function (b) {
                    b = this.validationTargetFor(this.clean(b));
                    var c,
                        d,
                        e,
                        f = a(b).rules(),
                        g = a.map(f, function (a, b) {
                            return b;
                        }).length,
                        h = !1,
                        i = this.elementValue(b);
                    if ("function" == typeof f.normalizer) {
                        if (((i = f.normalizer.call(b, i)), "string" != typeof i)) throw new TypeError("The normalizer should return a string value.");
                        delete f.normalizer;
                    }
                    for (d in f) {
                        e = { method: d, parameters: f[d] };
                        try {
                            if (((c = a.validator.methods[d].call(this, i, b, e.parameters)), "dependency-mismatch" === c && 1 === g)) {
                                h = !0;
                                continue;
                            }
                            if (((h = !1), "pending" === c)) return void (this.toHide = this.toHide.not(this.errorsFor(b)));
                            if (!c) return this.formatAndAdd(b, e), !1;
                        } catch (j) {
                            throw (
                                (this.settings.debug && window.console && console.log("Exception occurred when checking element " + b.id + ", check the '" + e.method + "' method.", j),
                                j instanceof TypeError && (j.message += ".  Exception occurred when checking element " + b.id + ", check the '" + e.method + "' method."),
                                j)
                            );
                        }
                    }
                    if (!h) return this.objectLength(f) && this.successList.push(b), !0;
                },
                customDataMessage: function (b, c) {
                    return a(b).data("msg" + c.charAt(0).toUpperCase() + c.substring(1).toLowerCase()) || a(b).data("msg");
                },
                customMessage: function (a, b) {
                    var c = this.settings.messages[a];
                    return c && (c.constructor === String ? c : c[b]);
                },
                findDefined: function () {
                    for (var a = 0; a < arguments.length; a++) if (void 0 !== arguments[a]) return arguments[a];
                },
                defaultMessage: function (b, c) {
                    var d = this.findDefined(
                            this.customMessage(b.name, c.method),
                            this.customDataMessage(b, c.method),
                            (!this.settings.ignoreTitle && b.title) || void 0,
                            a.validator.messages[c.method],
                            "<strong>Warning: No message defined for " + b.name + "</strong>"
                        ),
                        e = /\$?\{(\d+)\}/g;
                    return "function" == typeof d ? (d = d.call(this, c.parameters, b)) : e.test(d) && (d = a.validator.format(d.replace(e, "{$1}"), c.parameters)), d;
                },
                formatAndAdd: function (a, b) {
                    var c = this.defaultMessage(a, b);
                    this.errorList.push({ message: c, element: a, method: b.method }), (this.errorMap[a.name] = c), (this.submitted[a.name] = c);
                },
                addWrapper: function (a) {
                    return this.settings.wrapper && (a = a.add(a.parent(this.settings.wrapper))), a;
                },
                defaultShowErrors: function () {
                    var a, b, c;
                    for (a = 0; this.errorList[a]; a++)
                        (c = this.errorList[a]), this.settings.highlight && this.settings.highlight.call(this, c.element, this.settings.errorClass, this.settings.validClass), this.showLabel(c.element, c.message);
                    if ((this.errorList.length && (this.toShow = this.toShow.add(this.containers)), this.settings.success)) for (a = 0; this.successList[a]; a++) this.showLabel(this.successList[a]);
                    if (this.settings.unhighlight) for (a = 0, b = this.validElements(); b[a]; a++) this.settings.unhighlight.call(this, b[a], this.settings.errorClass, this.settings.validClass);
                    (this.toHide = this.toHide.not(this.toShow)), this.hideErrors(), this.addWrapper(this.toShow).show();
                },
                validElements: function () {
                    return this.currentElements.not(this.invalidElements());
                },
                invalidElements: function () {
                    return a(this.errorList).map(function () {
                        return this.element;
                    });
                },
                showLabel: function (b, c) {
                    var d,
                        e,
                        f,
                        g,
                        h = this.errorsFor(b),
                        i = this.idOrName(b),
                        j = a(b).attr("aria-describedby");
                    h.length
                        ? (h.removeClass(this.settings.validClass).addClass(this.settings.errorClass), h.html(c))
                        : ((h = a("<" + this.settings.errorElement + ">")
                              .attr("id", i + "-error")
                              .addClass(this.settings.errorClass)
                              .html(c || "")),
                          (d = h),
                          this.settings.wrapper &&
                              (d = h
                                  .hide()
                                  .show()
                                  .wrap("<" + this.settings.wrapper + "/>")
                                  .parent()),
                          this.labelContainer.length ? this.labelContainer.append(d) : this.settings.errorPlacement ? this.settings.errorPlacement(d, a(b)) : d.insertAfter(b),
                          h.is("label")
                              ? h.attr("for", i)
                              : 0 === h.parents("label[for='" + this.escapeCssMeta(i) + "']").length &&
                                ((f = h.attr("id")),
                                j ? j.match(new RegExp("\\b" + this.escapeCssMeta(f) + "\\b")) || (j += " " + f) : (j = f),
                                a(b).attr("aria-describedby", j),
                                (e = this.groups[b.name]),
                                e &&
                                    ((g = this),
                                    a.each(g.groups, function (b, c) {
                                        c === e && a("[name='" + g.escapeCssMeta(b) + "']", g.currentForm).attr("aria-describedby", h.attr("id"));
                                    })))),
                        !c && this.settings.success && (h.text(""), "string" == typeof this.settings.success ? h.addClass(this.settings.success) : this.settings.success(h, b)),
                        (this.toShow = this.toShow.add(h));
                },
                errorsFor: function (b) {
                    var c = this.escapeCssMeta(this.idOrName(b)),
                        d = a(b).attr("aria-describedby"),
                        e = "label[for='" + c + "'], label[for='" + c + "'] *";
                    return d && (e = e + ", #" + this.escapeCssMeta(d).replace(/\s+/g, ", #")), this.errors().filter(e);
                },
                escapeCssMeta: function (a) {
                    return a.replace(/([\\!"#$%&'()*+,./:;<=>?@\[\]^`{|}~])/g, "\\$1");
                },
                idOrName: function (a) {
                    return this.groups[a.name] || (this.checkable(a) ? a.name : a.id || a.name);
                },
                validationTargetFor: function (b) {
                    return this.checkable(b) && (b = this.findByName(b.name)), a(b).not(this.settings.ignore)[0];
                },
                checkable: function (a) {
                    return /radio|checkbox/i.test(a.type);
                },
                findByName: function (b) {
                    return a(this.currentForm).find("[name='" + this.escapeCssMeta(b) + "']");
                },
                getLength: function (b, c) {
                    switch (c.nodeName.toLowerCase()) {
                        case "select":
                            return a("option:selected", c).length;
                        case "input":
                            if (this.checkable(c)) return this.findByName(c.name).filter(":checked").length;
                    }
                    return b.length;
                },
                depend: function (a, b) {
                    return this.dependTypes[typeof a] ? this.dependTypes[typeof a](a, b) : !0;
                },
                dependTypes: {
                    boolean: function (a) {
                        return a;
                    },
                    string: function (b, c) {
                        return !!a(b, c.form).length;
                    },
                    function: function (a, b) {
                        return a(b);
                    },
                },
                optional: function (b) {
                    var c = this.elementValue(b);
                    return !a.validator.methods.required.call(this, c, b) && "dependency-mismatch";
                },
                startRequest: function (b) {
                    this.pending[b.name] || (this.pendingRequest++, a(b).addClass(this.settings.pendingClass), (this.pending[b.name] = !0));
                },
                stopRequest: function (b, c) {
                    this.pendingRequest--,
                        this.pendingRequest < 0 && (this.pendingRequest = 0),
                        delete this.pending[b.name],
                        a(b).removeClass(this.settings.pendingClass),
                        c && 0 === this.pendingRequest && this.formSubmitted && this.form()
                            ? (a(this.currentForm).submit(), (this.formSubmitted = !1))
                            : !c && 0 === this.pendingRequest && this.formSubmitted && (a(this.currentForm).triggerHandler("invalid-form", [this]), (this.formSubmitted = !1));
                },
                previousValue: function (b, c) {
                    return a.data(b, "previousValue") || a.data(b, "previousValue", { old: null, valid: !0, message: this.defaultMessage(b, { method: c }) });
                },
                destroy: function () {
                    this.resetForm(), a(this.currentForm).off(".validate").removeData("validator").find(".validate-equalTo-blur").off(".validate-equalTo").removeClass("validate-equalTo-blur");
                },
            },
            classRuleSettings: { required: { required: !0 }, email: { email: !0 }, url: { url: !0 }, date: { date: !0 }, dateISO: { dateISO: !0 }, number: { number: !0 }, digits: { digits: !0 }, creditcard: { creditcard: !0 } },
            addClassRules: function (b, c) {
                b.constructor === String ? (this.classRuleSettings[b] = c) : a.extend(this.classRuleSettings, b);
            },
            classRules: function (b) {
                var c = {},
                    d = a(b).attr("class");
                return (
                    d &&
                        a.each(d.split(" "), function () {
                            this in a.validator.classRuleSettings && a.extend(c, a.validator.classRuleSettings[this]);
                        }),
                    c
                );
            },
            normalizeAttributeRule: function (a, b, c, d) {
                /min|max|step/.test(c) && (null === b || /number|range|text/.test(b)) && ((d = Number(d)), isNaN(d) && (d = void 0)), d || 0 === d ? (a[c] = d) : b === c && "range" !== b && (a[c] = !0);
            },
            attributeRules: function (b) {
                var c,
                    d,
                    e = {},
                    f = a(b),
                    g = b.getAttribute("type");
                for (c in a.validator.methods) "required" === c ? ((d = b.getAttribute(c)), "" === d && (d = !0), (d = !!d)) : (d = f.attr(c)), this.normalizeAttributeRule(e, g, c, d);
                return e.maxlength && /-1|2147483647|524288/.test(e.maxlength) && delete e.maxlength, e;
            },
            dataRules: function (b) {
                var c,
                    d,
                    e = {},
                    f = a(b),
                    g = b.getAttribute("type");
                for (c in a.validator.methods) (d = f.data("rule" + c.charAt(0).toUpperCase() + c.substring(1).toLowerCase())), this.normalizeAttributeRule(e, g, c, d);
                return e;
            },
            staticRules: function (b) {
                var c = {},
                    d = a.data(b.form, "validator");
                return d.settings.rules && (c = a.validator.normalizeRule(d.settings.rules[b.name]) || {}), c;
            },
            normalizeRules: function (b, c) {
                return (
                    a.each(b, function (d, e) {
                        if (e === !1) return void delete b[d];
                        if (e.param || e.depends) {
                            var f = !0;
                            switch (typeof e.depends) {
                                case "string":
                                    f = !!a(e.depends, c.form).length;
                                    break;
                                case "function":
                                    f = e.depends.call(c, c);
                            }
                            f ? (b[d] = void 0 !== e.param ? e.param : !0) : (a.data(c.form, "validator").resetElements(a(c)), delete b[d]);
                        }
                    }),
                    a.each(b, function (d, e) {
                        b[d] = a.isFunction(e) && "normalizer" !== d ? e(c) : e;
                    }),
                    a.each(["minlength", "maxlength"], function () {
                        b[this] && (b[this] = Number(b[this]));
                    }),
                    a.each(["rangelength", "range"], function () {
                        var c;
                        b[this] && (a.isArray(b[this]) ? (b[this] = [Number(b[this][0]), Number(b[this][1])]) : "string" == typeof b[this] && ((c = b[this].replace(/[\[\]]/g, "").split(/[\s,]+/)), (b[this] = [Number(c[0]), Number(c[1])])));
                    }),
                    a.validator.autoCreateRanges &&
                        (null != b.min && null != b.max && ((b.range = [b.min, b.max]), delete b.min, delete b.max),
                        null != b.minlength && null != b.maxlength && ((b.rangelength = [b.minlength, b.maxlength]), delete b.minlength, delete b.maxlength)),
                    b
                );
            },
            normalizeRule: function (b) {
                if ("string" == typeof b) {
                    var c = {};
                    a.each(b.split(/\s/), function () {
                        c[this] = !0;
                    }),
                        (b = c);
                }
                return b;
            },
            addMethod: function (b, c, d) {
                (a.validator.methods[b] = c), (a.validator.messages[b] = void 0 !== d ? d : a.validator.messages[b]), c.length < 3 && a.validator.addClassRules(b, a.validator.normalizeRule(b));
            },
            methods: {
                required: function (b, c, d) {
                    if (!this.depend(d, c)) return "dependency-mismatch";
                    if ("select" === c.nodeName.toLowerCase()) {
                        var e = a(c).val();
                        return e && e.length > 0;
                    }
                    return this.checkable(c) ? this.getLength(b, c) > 0 : b.length > 0;
                },
                email: function (a, b) {
                    return this.optional(b) || /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/.test(a);
                },
                url: function (a, b) {
                    return (
                        this.optional(b) ||
                        /^(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})).?)(?::\d{2,5})?(?:[/?#]\S*)?$/i.test(
                            a
                        )
                    );
                },
                date: function (a, b) {
                    return this.optional(b) || !/Invalid|NaN/.test(new Date(a).toString());
                },
                dateISO: function (a, b) {
                    return this.optional(b) || /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$/.test(a);
                },
                number: function (a, b) {
                    return this.optional(b) || /^(?:-?\d+|-?\d{1,3}(?:,\d{3})+)?(?:\.\d+)?$/.test(a);
                },
                digits: function (a, b) {
                    return this.optional(b) || /^\d+$/.test(a);
                },
                minlength: function (b, c, d) {
                    var e = a.isArray(b) ? b.length : this.getLength(b, c);
                    return this.optional(c) || e >= d;
                },
                maxlength: function (b, c, d) {
                    var e = a.isArray(b) ? b.length : this.getLength(b, c);
                    return this.optional(c) || d >= e;
                },
                rangelength: function (b, c, d) {
                    var e = a.isArray(b) ? b.length : this.getLength(b, c);
                    return this.optional(c) || (e >= d[0] && e <= d[1]);
                },
                min: function (a, b, c) {
                    return this.optional(b) || a >= c;
                },
                max: function (a, b, c) {
                    return this.optional(b) || c >= a;
                },
                range: function (a, b, c) {
                    return this.optional(b) || (a >= c[0] && a <= c[1]);
                },
                step: function (b, c, d) {
                    var e = a(c).attr("type"),
                        f = "Step attribute on input type " + e + " is not supported.",
                        g = ["text", "number", "range"],
                        h = new RegExp("\\b" + e + "\\b"),
                        i = e && !h.test(g.join());
                    if (i) throw new Error(f);
                    return this.optional(c) || b % d === 0;
                },
                equalTo: function (b, c, d) {
                    var e = a(d);
                    return (
                        this.settings.onfocusout &&
                            e.not(".validate-equalTo-blur").length &&
                            e.addClass("validate-equalTo-blur").on("blur.validate-equalTo", function () {
                                a(c).valid();
                            }),
                        b === e.val()
                    );
                },
                remote: function (b, c, d, e) {
                    if (this.optional(c)) return "dependency-mismatch";
                    e = ("string" == typeof e && e) || "remote";
                    var f,
                        g,
                        h,
                        i = this.previousValue(c, e);
                    return (
                        this.settings.messages[c.name] || (this.settings.messages[c.name] = {}),
                        (i.originalMessage = i.originalMessage || this.settings.messages[c.name][e]),
                        (this.settings.messages[c.name][e] = i.message),
                        (d = ("string" == typeof d && { url: d }) || d),
                        (h = a.param(a.extend({ data: b }, d.data))),
                        i.old === h
                            ? i.valid
                            : ((i.old = h),
                              (f = this),
                              this.startRequest(c),
                              (g = {}),
                              (g[c.name] = b),
                              a.ajax(
                                  a.extend(
                                      !0,
                                      {
                                          mode: "abort",
                                          port: "validate" + c.name,
                                          dataType: "json",
                                          data: g,
                                          context: f.currentForm,
                                          success: function (a) {
                                              var d,
                                                  g,
                                                  h,
                                                  j = a === !0 || "true" === a;
                                              (f.settings.messages[c.name][e] = i.originalMessage),
                                                  j
                                                      ? ((h = f.formSubmitted), f.resetInternals(), (f.toHide = f.errorsFor(c)), (f.formSubmitted = h), f.successList.push(c), (f.invalid[c.name] = !1), f.showErrors())
                                                      : ((d = {}), (g = a || f.defaultMessage(c, { method: e, parameters: b })), (d[c.name] = i.message = g), (f.invalid[c.name] = !0), f.showErrors(d)),
                                                  (i.valid = j),
                                                  f.stopRequest(c, j);
                                          },
                                      },
                                      d
                                  )
                              ),
                              "pending")
                    );
                },
            },
        });
    var b,
        c = {};
    a.ajaxPrefilter
        ? a.ajaxPrefilter(function (a, b, d) {
              var e = a.port;
              "abort" === a.mode && (c[e] && c[e].abort(), (c[e] = d));
          })
        : ((b = a.ajax),
          (a.ajax = function (d) {
              var e = ("mode" in d ? d : a.ajaxSettings).mode,
                  f = ("port" in d ? d : a.ajaxSettings).port;
              return "abort" === e ? (c[f] && c[f].abort(), (c[f] = b.apply(this, arguments)), c[f]) : b.apply(this, arguments);
          }));
});

/*jquery.qrcode*/
(function (r) {
    r.fn.qrcode = function (h) {
        var s;
        function u(a) {
            this.mode = s;
            this.data = a;
        }
        function o(a, c) {
            this.typeNumber = a;
            this.errorCorrectLevel = c;
            this.modules = null;
            this.moduleCount = 0;
            this.dataCache = null;
            this.dataList = [];
        }
        function q(a, c) {
            if (void 0 == a.length) throw Error(a.length + "/" + c);
            for (var d = 0; d < a.length && 0 == a[d]; ) d++;
            this.num = Array(a.length - d + c);
            for (var b = 0; b < a.length - d; b++) this.num[b] = a[b + d];
        }
        function p(a, c) {
            this.totalCount = a;
            this.dataCount = c;
        }
        function t() {
            this.buffer = [];
            this.length = 0;
        }
        u.prototype = {
            getLength: function () {
                return this.data.length;
            },
            write: function (a) {
                for (var c = 0; c < this.data.length; c++) a.put(this.data.charCodeAt(c), 8);
            },
        };
        o.prototype = {
            addData: function (a) {
                this.dataList.push(new u(a));
                this.dataCache = null;
            },
            isDark: function (a, c) {
                if (0 > a || this.moduleCount <= a || 0 > c || this.moduleCount <= c) throw Error(a + "," + c);
                return this.modules[a][c];
            },
            getModuleCount: function () {
                return this.moduleCount;
            },
            make: function () {
                if (1 > this.typeNumber) {
                    for (var a = 1, a = 1; 40 > a; a++) {
                        for (var c = p.getRSBlocks(a, this.errorCorrectLevel), d = new t(), b = 0, e = 0; e < c.length; e++) b += c[e].dataCount;
                        for (e = 0; e < this.dataList.length; e++) (c = this.dataList[e]), d.put(c.mode, 4), d.put(c.getLength(), j.getLengthInBits(c.mode, a)), c.write(d);
                        if (d.getLengthInBits() <= 8 * b) break;
                    }
                    this.typeNumber = a;
                }
                this.makeImpl(!1, this.getBestMaskPattern());
            },
            makeImpl: function (a, c) {
                this.moduleCount = 4 * this.typeNumber + 17;
                this.modules = Array(this.moduleCount);
                for (var d = 0; d < this.moduleCount; d++) {
                    this.modules[d] = Array(this.moduleCount);
                    for (var b = 0; b < this.moduleCount; b++) this.modules[d][b] = null;
                }
                this.setupPositionProbePattern(0, 0);
                this.setupPositionProbePattern(this.moduleCount - 7, 0);
                this.setupPositionProbePattern(0, this.moduleCount - 7);
                this.setupPositionAdjustPattern();
                this.setupTimingPattern();
                this.setupTypeInfo(a, c);
                7 <= this.typeNumber && this.setupTypeNumber(a);
                null == this.dataCache && (this.dataCache = o.createData(this.typeNumber, this.errorCorrectLevel, this.dataList));
                this.mapData(this.dataCache, c);
            },
            setupPositionProbePattern: function (a, c) {
                for (var d = -1; 7 >= d; d++)
                    if (!(-1 >= a + d || this.moduleCount <= a + d))
                        for (var b = -1; 7 >= b; b++)
                            -1 >= c + b || this.moduleCount <= c + b || (this.modules[a + d][c + b] = (0 <= d && 6 >= d && (0 == b || 6 == b)) || (0 <= b && 6 >= b && (0 == d || 6 == d)) || (2 <= d && 4 >= d && 2 <= b && 4 >= b) ? !0 : !1);
            },
            getBestMaskPattern: function () {
                for (var a = 0, c = 0, d = 0; 8 > d; d++) {
                    this.makeImpl(!0, d);
                    var b = j.getLostPoint(this);
                    if (0 == d || a > b) (a = b), (c = d);
                }
                return c;
            },
            createMovieClip: function (a, c, d) {
                a = a.createEmptyMovieClip(c, d);
                this.make();
                for (c = 0; c < this.modules.length; c++)
                    for (var d = 1 * c, b = 0; b < this.modules[c].length; b++) {
                        var e = 1 * b;
                        this.modules[c][b] && (a.beginFill(0, 100), a.moveTo(e, d), a.lineTo(e + 1, d), a.lineTo(e + 1, d + 1), a.lineTo(e, d + 1), a.endFill());
                    }
                return a;
            },
            setupTimingPattern: function () {
                for (var a = 8; a < this.moduleCount - 8; a++) null == this.modules[a][6] && (this.modules[a][6] = 0 == a % 2);
                for (a = 8; a < this.moduleCount - 8; a++) null == this.modules[6][a] && (this.modules[6][a] = 0 == a % 2);
            },
            setupPositionAdjustPattern: function () {
                for (var a = j.getPatternPosition(this.typeNumber), c = 0; c < a.length; c++)
                    for (var d = 0; d < a.length; d++) {
                        var b = a[c],
                            e = a[d];
                        if (null == this.modules[b][e]) for (var f = -2; 2 >= f; f++) for (var i = -2; 2 >= i; i++) this.modules[b + f][e + i] = -2 == f || 2 == f || -2 == i || 2 == i || (0 == f && 0 == i) ? !0 : !1;
                    }
            },
            setupTypeNumber: function (a) {
                for (var c = j.getBCHTypeNumber(this.typeNumber), d = 0; 18 > d; d++) {
                    var b = !a && 1 == ((c >> d) & 1);
                    this.modules[Math.floor(d / 3)][(d % 3) + this.moduleCount - 8 - 3] = b;
                }
                for (d = 0; 18 > d; d++) (b = !a && 1 == ((c >> d) & 1)), (this.modules[(d % 3) + this.moduleCount - 8 - 3][Math.floor(d / 3)] = b);
            },
            setupTypeInfo: function (a, c) {
                for (var d = j.getBCHTypeInfo((this.errorCorrectLevel << 3) | c), b = 0; 15 > b; b++) {
                    var e = !a && 1 == ((d >> b) & 1);
                    6 > b ? (this.modules[b][8] = e) : 8 > b ? (this.modules[b + 1][8] = e) : (this.modules[this.moduleCount - 15 + b][8] = e);
                }
                for (b = 0; 15 > b; b++) (e = !a && 1 == ((d >> b) & 1)), 8 > b ? (this.modules[8][this.moduleCount - b - 1] = e) : 9 > b ? (this.modules[8][15 - b - 1 + 1] = e) : (this.modules[8][15 - b - 1] = e);
                this.modules[this.moduleCount - 8][8] = !a;
            },
            mapData: function (a, c) {
                for (var d = -1, b = this.moduleCount - 1, e = 7, f = 0, i = this.moduleCount - 1; 0 < i; i -= 2)
                    for (6 == i && i--; ; ) {
                        for (var g = 0; 2 > g; g++)
                            if (null == this.modules[b][i - g]) {
                                var n = !1;
                                f < a.length && (n = 1 == ((a[f] >>> e) & 1));
                                j.getMask(c, b, i - g) && (n = !n);
                                this.modules[b][i - g] = n;
                                e--;
                                -1 == e && (f++, (e = 7));
                            }
                        b += d;
                        if (0 > b || this.moduleCount <= b) {
                            b -= d;
                            d = -d;
                            break;
                        }
                    }
            },
        };
        o.PAD0 = 236;
        o.PAD1 = 17;
        o.createData = function (a, c, d) {
            for (var c = p.getRSBlocks(a, c), b = new t(), e = 0; e < d.length; e++) {
                var f = d[e];
                b.put(f.mode, 4);
                b.put(f.getLength(), j.getLengthInBits(f.mode, a));
                f.write(b);
            }
            for (e = a = 0; e < c.length; e++) a += c[e].dataCount;
            if (b.getLengthInBits() > 8 * a) throw Error("code length overflow. (" + b.getLengthInBits() + ">" + 8 * a + ")");
            for (b.getLengthInBits() + 4 <= 8 * a && b.put(0, 4); 0 != b.getLengthInBits() % 8; ) b.putBit(!1);
            for (; !(b.getLengthInBits() >= 8 * a); ) {
                b.put(o.PAD0, 8);
                if (b.getLengthInBits() >= 8 * a) break;
                b.put(o.PAD1, 8);
            }
            return o.createBytes(b, c);
        };
        o.createBytes = function (a, c) {
            for (var d = 0, b = 0, e = 0, f = Array(c.length), i = Array(c.length), g = 0; g < c.length; g++) {
                var n = c[g].dataCount,
                    h = c[g].totalCount - n,
                    b = Math.max(b, n),
                    e = Math.max(e, h);
                f[g] = Array(n);
                for (var k = 0; k < f[g].length; k++) f[g][k] = 255 & a.buffer[k + d];
                d += n;
                k = j.getErrorCorrectPolynomial(h);
                n = new q(f[g], k.getLength() - 1).mod(k);
                i[g] = Array(k.getLength() - 1);
                for (k = 0; k < i[g].length; k++) (h = k + n.getLength() - i[g].length), (i[g][k] = 0 <= h ? n.get(h) : 0);
            }
            for (k = g = 0; k < c.length; k++) g += c[k].totalCount;
            d = Array(g);
            for (k = n = 0; k < b; k++) for (g = 0; g < c.length; g++) k < f[g].length && (d[n++] = f[g][k]);
            for (k = 0; k < e; k++) for (g = 0; g < c.length; g++) k < i[g].length && (d[n++] = i[g][k]);
            return d;
        };
        s = 4;
        for (
            var j = {
                    PATTERN_POSITION_TABLE: [
                        [],
                        [6, 18],
                        [6, 22],
                        [6, 26],
                        [6, 30],
                        [6, 34],
                        [6, 22, 38],
                        [6, 24, 42],
                        [6, 26, 46],
                        [6, 28, 50],
                        [6, 30, 54],
                        [6, 32, 58],
                        [6, 34, 62],
                        [6, 26, 46, 66],
                        [6, 26, 48, 70],
                        [6, 26, 50, 74],
                        [6, 30, 54, 78],
                        [6, 30, 56, 82],
                        [6, 30, 58, 86],
                        [6, 34, 62, 90],
                        [6, 28, 50, 72, 94],
                        [6, 26, 50, 74, 98],
                        [6, 30, 54, 78, 102],
                        [6, 28, 54, 80, 106],
                        [6, 32, 58, 84, 110],
                        [6, 30, 58, 86, 114],
                        [6, 34, 62, 90, 118],
                        [6, 26, 50, 74, 98, 122],
                        [6, 30, 54, 78, 102, 126],
                        [6, 26, 52, 78, 104, 130],
                        [6, 30, 56, 82, 108, 134],
                        [6, 34, 60, 86, 112, 138],
                        [6, 30, 58, 86, 114, 142],
                        [6, 34, 62, 90, 118, 146],
                        [6, 30, 54, 78, 102, 126, 150],
                        [6, 24, 50, 76, 102, 128, 154],
                        [6, 28, 54, 80, 106, 132, 158],
                        [6, 32, 58, 84, 110, 136, 162],
                        [6, 26, 54, 82, 110, 138, 166],
                        [6, 30, 58, 86, 114, 142, 170],
                    ],
                    G15: 1335,
                    G18: 7973,
                    G15_MASK: 21522,
                    getBCHTypeInfo: function (a) {
                        for (var c = a << 10; 0 <= j.getBCHDigit(c) - j.getBCHDigit(j.G15); ) c ^= j.G15 << (j.getBCHDigit(c) - j.getBCHDigit(j.G15));
                        return ((a << 10) | c) ^ j.G15_MASK;
                    },
                    getBCHTypeNumber: function (a) {
                        for (var c = a << 12; 0 <= j.getBCHDigit(c) - j.getBCHDigit(j.G18); ) c ^= j.G18 << (j.getBCHDigit(c) - j.getBCHDigit(j.G18));
                        return (a << 12) | c;
                    },
                    getBCHDigit: function (a) {
                        for (var c = 0; 0 != a; ) c++, (a >>>= 1);
                        return c;
                    },
                    getPatternPosition: function (a) {
                        return j.PATTERN_POSITION_TABLE[a - 1];
                    },
                    getMask: function (a, c, d) {
                        switch (a) {
                            case 0:
                                return 0 == (c + d) % 2;
                            case 1:
                                return 0 == c % 2;
                            case 2:
                                return 0 == d % 3;
                            case 3:
                                return 0 == (c + d) % 3;
                            case 4:
                                return 0 == (Math.floor(c / 2) + Math.floor(d / 3)) % 2;
                            case 5:
                                return 0 == ((c * d) % 2) + ((c * d) % 3);
                            case 6:
                                return 0 == (((c * d) % 2) + ((c * d) % 3)) % 2;
                            case 7:
                                return 0 == (((c * d) % 3) + ((c + d) % 2)) % 2;
                            default:
                                throw Error("bad maskPattern:" + a);
                        }
                    },
                    getErrorCorrectPolynomial: function (a) {
                        for (var c = new q([1], 0), d = 0; d < a; d++) c = c.multiply(new q([1, l.gexp(d)], 0));
                        return c;
                    },
                    getLengthInBits: function (a, c) {
                        if (1 <= c && 10 > c)
                            switch (a) {
                                case 1:
                                    return 10;
                                case 2:
                                    return 9;
                                case s:
                                    return 8;
                                case 8:
                                    return 8;
                                default:
                                    throw Error("mode:" + a);
                            }
                        else if (27 > c)
                            switch (a) {
                                case 1:
                                    return 12;
                                case 2:
                                    return 11;
                                case s:
                                    return 16;
                                case 8:
                                    return 10;
                                default:
                                    throw Error("mode:" + a);
                            }
                        else if (41 > c)
                            switch (a) {
                                case 1:
                                    return 14;
                                case 2:
                                    return 13;
                                case s:
                                    return 16;
                                case 8:
                                    return 12;
                                default:
                                    throw Error("mode:" + a);
                            }
                        else throw Error("type:" + c);
                    },
                    getLostPoint: function (a) {
                        for (var c = a.getModuleCount(), d = 0, b = 0; b < c; b++)
                            for (var e = 0; e < c; e++) {
                                for (var f = 0, i = a.isDark(b, e), g = -1; 1 >= g; g++) if (!(0 > b + g || c <= b + g)) for (var h = -1; 1 >= h; h++) 0 > e + h || c <= e + h || (0 == g && 0 == h) || (i == a.isDark(b + g, e + h) && f++);
                                5 < f && (d += 3 + f - 5);
                            }
                        for (b = 0; b < c - 1; b++) for (e = 0; e < c - 1; e++) if (((f = 0), a.isDark(b, e) && f++, a.isDark(b + 1, e) && f++, a.isDark(b, e + 1) && f++, a.isDark(b + 1, e + 1) && f++, 0 == f || 4 == f)) d += 3;
                        for (b = 0; b < c; b++) for (e = 0; e < c - 6; e++) a.isDark(b, e) && !a.isDark(b, e + 1) && a.isDark(b, e + 2) && a.isDark(b, e + 3) && a.isDark(b, e + 4) && !a.isDark(b, e + 5) && a.isDark(b, e + 6) && (d += 40);
                        for (e = 0; e < c; e++) for (b = 0; b < c - 6; b++) a.isDark(b, e) && !a.isDark(b + 1, e) && a.isDark(b + 2, e) && a.isDark(b + 3, e) && a.isDark(b + 4, e) && !a.isDark(b + 5, e) && a.isDark(b + 6, e) && (d += 40);
                        for (e = f = 0; e < c; e++) for (b = 0; b < c; b++) a.isDark(b, e) && f++;
                        a = Math.abs((100 * f) / c / c - 50) / 5;
                        return d + 10 * a;
                    },
                },
                l = {
                    glog: function (a) {
                        if (1 > a) throw Error("glog(" + a + ")");
                        return l.LOG_TABLE[a];
                    },
                    gexp: function (a) {
                        for (; 0 > a; ) a += 255;
                        for (; 256 <= a; ) a -= 255;
                        return l.EXP_TABLE[a];
                    },
                    EXP_TABLE: Array(256),
                    LOG_TABLE: Array(256),
                },
                m = 0;
            8 > m;
            m++
        )
            l.EXP_TABLE[m] = 1 << m;
        for (m = 8; 256 > m; m++) l.EXP_TABLE[m] = l.EXP_TABLE[m - 4] ^ l.EXP_TABLE[m - 5] ^ l.EXP_TABLE[m - 6] ^ l.EXP_TABLE[m - 8];
        for (m = 0; 255 > m; m++) l.LOG_TABLE[l.EXP_TABLE[m]] = m;
        q.prototype = {
            get: function (a) {
                return this.num[a];
            },
            getLength: function () {
                return this.num.length;
            },
            multiply: function (a) {
                for (var c = Array(this.getLength() + a.getLength() - 1), d = 0; d < this.getLength(); d++) for (var b = 0; b < a.getLength(); b++) c[d + b] ^= l.gexp(l.glog(this.get(d)) + l.glog(a.get(b)));
                return new q(c, 0);
            },
            mod: function (a) {
                if (0 > this.getLength() - a.getLength()) return this;
                for (var c = l.glog(this.get(0)) - l.glog(a.get(0)), d = Array(this.getLength()), b = 0; b < this.getLength(); b++) d[b] = this.get(b);
                for (b = 0; b < a.getLength(); b++) d[b] ^= l.gexp(l.glog(a.get(b)) + c);
                return new q(d, 0).mod(a);
            },
        };
        p.RS_BLOCK_TABLE = [
            [1, 26, 19],
            [1, 26, 16],
            [1, 26, 13],
            [1, 26, 9],
            [1, 44, 34],
            [1, 44, 28],
            [1, 44, 22],
            [1, 44, 16],
            [1, 70, 55],
            [1, 70, 44],
            [2, 35, 17],
            [2, 35, 13],
            [1, 100, 80],
            [2, 50, 32],
            [2, 50, 24],
            [4, 25, 9],
            [1, 134, 108],
            [2, 67, 43],
            [2, 33, 15, 2, 34, 16],
            [2, 33, 11, 2, 34, 12],
            [2, 86, 68],
            [4, 43, 27],
            [4, 43, 19],
            [4, 43, 15],
            [2, 98, 78],
            [4, 49, 31],
            [2, 32, 14, 4, 33, 15],
            [4, 39, 13, 1, 40, 14],
            [2, 121, 97],
            [2, 60, 38, 2, 61, 39],
            [4, 40, 18, 2, 41, 19],
            [4, 40, 14, 2, 41, 15],
            [2, 146, 116],
            [3, 58, 36, 2, 59, 37],
            [4, 36, 16, 4, 37, 17],
            [4, 36, 12, 4, 37, 13],
            [2, 86, 68, 2, 87, 69],
            [4, 69, 43, 1, 70, 44],
            [6, 43, 19, 2, 44, 20],
            [6, 43, 15, 2, 44, 16],
            [4, 101, 81],
            [1, 80, 50, 4, 81, 51],
            [4, 50, 22, 4, 51, 23],
            [3, 36, 12, 8, 37, 13],
            [2, 116, 92, 2, 117, 93],
            [6, 58, 36, 2, 59, 37],
            [4, 46, 20, 6, 47, 21],
            [7, 42, 14, 4, 43, 15],
            [4, 133, 107],
            [8, 59, 37, 1, 60, 38],
            [8, 44, 20, 4, 45, 21],
            [12, 33, 11, 4, 34, 12],
            [3, 145, 115, 1, 146, 116],
            [4, 64, 40, 5, 65, 41],
            [11, 36, 16, 5, 37, 17],
            [11, 36, 12, 5, 37, 13],
            [5, 109, 87, 1, 110, 88],
            [5, 65, 41, 5, 66, 42],
            [5, 54, 24, 7, 55, 25],
            [11, 36, 12],
            [5, 122, 98, 1, 123, 99],
            [7, 73, 45, 3, 74, 46],
            [15, 43, 19, 2, 44, 20],
            [3, 45, 15, 13, 46, 16],
            [1, 135, 107, 5, 136, 108],
            [10, 74, 46, 1, 75, 47],
            [1, 50, 22, 15, 51, 23],
            [2, 42, 14, 17, 43, 15],
            [5, 150, 120, 1, 151, 121],
            [9, 69, 43, 4, 70, 44],
            [17, 50, 22, 1, 51, 23],
            [2, 42, 14, 19, 43, 15],
            [3, 141, 113, 4, 142, 114],
            [3, 70, 44, 11, 71, 45],
            [17, 47, 21, 4, 48, 22],
            [9, 39, 13, 16, 40, 14],
            [3, 135, 107, 5, 136, 108],
            [3, 67, 41, 13, 68, 42],
            [15, 54, 24, 5, 55, 25],
            [15, 43, 15, 10, 44, 16],
            [4, 144, 116, 4, 145, 117],
            [17, 68, 42],
            [17, 50, 22, 6, 51, 23],
            [19, 46, 16, 6, 47, 17],
            [2, 139, 111, 7, 140, 112],
            [17, 74, 46],
            [7, 54, 24, 16, 55, 25],
            [34, 37, 13],
            [4, 151, 121, 5, 152, 122],
            [4, 75, 47, 14, 76, 48],
            [11, 54, 24, 14, 55, 25],
            [16, 45, 15, 14, 46, 16],
            [6, 147, 117, 4, 148, 118],
            [6, 73, 45, 14, 74, 46],
            [11, 54, 24, 16, 55, 25],
            [30, 46, 16, 2, 47, 17],
            [8, 132, 106, 4, 133, 107],
            [8, 75, 47, 13, 76, 48],
            [7, 54, 24, 22, 55, 25],
            [22, 45, 15, 13, 46, 16],
            [10, 142, 114, 2, 143, 115],
            [19, 74, 46, 4, 75, 47],
            [28, 50, 22, 6, 51, 23],
            [33, 46, 16, 4, 47, 17],
            [8, 152, 122, 4, 153, 123],
            [22, 73, 45, 3, 74, 46],
            [8, 53, 23, 26, 54, 24],
            [12, 45, 15, 28, 46, 16],
            [3, 147, 117, 10, 148, 118],
            [3, 73, 45, 23, 74, 46],
            [4, 54, 24, 31, 55, 25],
            [11, 45, 15, 31, 46, 16],
            [7, 146, 116, 7, 147, 117],
            [21, 73, 45, 7, 74, 46],
            [1, 53, 23, 37, 54, 24],
            [19, 45, 15, 26, 46, 16],
            [5, 145, 115, 10, 146, 116],
            [19, 75, 47, 10, 76, 48],
            [15, 54, 24, 25, 55, 25],
            [23, 45, 15, 25, 46, 16],
            [13, 145, 115, 3, 146, 116],
            [2, 74, 46, 29, 75, 47],
            [42, 54, 24, 1, 55, 25],
            [23, 45, 15, 28, 46, 16],
            [17, 145, 115],
            [10, 74, 46, 23, 75, 47],
            [10, 54, 24, 35, 55, 25],
            [19, 45, 15, 35, 46, 16],
            [17, 145, 115, 1, 146, 116],
            [14, 74, 46, 21, 75, 47],
            [29, 54, 24, 19, 55, 25],
            [11, 45, 15, 46, 46, 16],
            [13, 145, 115, 6, 146, 116],
            [14, 74, 46, 23, 75, 47],
            [44, 54, 24, 7, 55, 25],
            [59, 46, 16, 1, 47, 17],
            [12, 151, 121, 7, 152, 122],
            [12, 75, 47, 26, 76, 48],
            [39, 54, 24, 14, 55, 25],
            [22, 45, 15, 41, 46, 16],
            [6, 151, 121, 14, 152, 122],
            [6, 75, 47, 34, 76, 48],
            [46, 54, 24, 10, 55, 25],
            [2, 45, 15, 64, 46, 16],
            [17, 152, 122, 4, 153, 123],
            [29, 74, 46, 14, 75, 47],
            [49, 54, 24, 10, 55, 25],
            [24, 45, 15, 46, 46, 16],
            [4, 152, 122, 18, 153, 123],
            [13, 74, 46, 32, 75, 47],
            [48, 54, 24, 14, 55, 25],
            [42, 45, 15, 32, 46, 16],
            [20, 147, 117, 4, 148, 118],
            [40, 75, 47, 7, 76, 48],
            [43, 54, 24, 22, 55, 25],
            [10, 45, 15, 67, 46, 16],
            [19, 148, 118, 6, 149, 119],
            [18, 75, 47, 31, 76, 48],
            [34, 54, 24, 34, 55, 25],
            [20, 45, 15, 61, 46, 16],
        ];
        p.getRSBlocks = function (a, c) {
            var d = p.getRsBlockTable(a, c);
            if (void 0 == d) throw Error("bad rs block @ typeNumber:" + a + "/errorCorrectLevel:" + c);
            for (var b = d.length / 3, e = [], f = 0; f < b; f++) for (var h = d[3 * f + 0], g = d[3 * f + 1], j = d[3 * f + 2], l = 0; l < h; l++) e.push(new p(g, j));
            return e;
        };
        p.getRsBlockTable = function (a, c) {
            switch (c) {
                case 1:
                    return p.RS_BLOCK_TABLE[4 * (a - 1) + 0];
                case 0:
                    return p.RS_BLOCK_TABLE[4 * (a - 1) + 1];
                case 3:
                    return p.RS_BLOCK_TABLE[4 * (a - 1) + 2];
                case 2:
                    return p.RS_BLOCK_TABLE[4 * (a - 1) + 3];
            }
        };
        t.prototype = {
            get: function (a) {
                return 1 == ((this.buffer[Math.floor(a / 8)] >>> (7 - (a % 8))) & 1);
            },
            put: function (a, c) {
                for (var d = 0; d < c; d++) this.putBit(1 == ((a >>> (c - d - 1)) & 1));
            },
            getLengthInBits: function () {
                return this.length;
            },
            putBit: function (a) {
                var c = Math.floor(this.length / 8);
                this.buffer.length <= c && this.buffer.push(0);
                a && (this.buffer[c] |= 128 >>> this.length % 8);
                this.length++;
            },
        };
        "string" === typeof h && (h = { text: h });
        h = r.extend({}, { render: "canvas", width: 256, height: 256, typeNumber: -1, correctLevel: 2, background: "#ffffff", foreground: "#000000" }, h);
        return this.each(function () {
            var a;
            if ("canvas" == h.render) {
                a = new o(h.typeNumber, h.correctLevel);
                a.addData(h.text);
                a.make();
                var c = document.createElement("canvas");
                c.width = h.width;
                c.height = h.height;
                for (var d = c.getContext("2d"), b = h.width / a.getModuleCount(), e = h.height / a.getModuleCount(), f = 0; f < a.getModuleCount(); f++)
                    for (var i = 0; i < a.getModuleCount(); i++) {
                        d.fillStyle = a.isDark(f, i) ? h.foreground : h.background;
                        var g = Math.ceil((i + 1) * b) - Math.floor(i * b),
                            j = Math.ceil((f + 1) * b) - Math.floor(f * b);
                        d.fillRect(Math.round(i * b), Math.round(f * e), g, j);
                    }
            } else {
                a = new o(h.typeNumber, h.correctLevel);
                a.addData(h.text);
                a.make();
                c = r("<table></table>")
                    .css("width", h.width + "px")
                    .css("height", h.height + "px")
                    .css("border", "0px")
                    .css("border-collapse", "collapse")
                    .css("background-color", h.background);
                d = h.width / a.getModuleCount();
                b = h.height / a.getModuleCount();
                for (e = 0; e < a.getModuleCount(); e++) {
                    f = r("<tr></tr>")
                        .css("height", b + "px")
                        .appendTo(c);
                    for (i = 0; i < a.getModuleCount(); i++)
                        r("<td></td>")
                            .css("width", d + "px")
                            .css("background-color", a.isDark(e, i) ? h.foreground : h.background)
                            .appendTo(f);
                }
            }
            a = c;
            jQuery(a).appendTo(this);
        });
    };
})(jQuery);

$("document").ready(function ($) {
    AOS.init();
    var banner = $(".banner-section");
    var nav = $(".site-header");
    var resetMenuHeader = function () {
        if ($(this).scrollTop() > banner.height()) {
            nav.removeClass("d-none").addClass("f-nav");
        } else {
            nav.addClass("d-none").removeClass("f-nav");
        }
    };
    resetMenuHeader();
    $(window).scroll(function () {
        resetMenuHeader();
    });

    if ($("#clock").length) {
        function timeElapse(date) {
            var current = Date();
            var seconds = (Date.parse(current) - Date.parse(date)) / 1000;
            var days = Math.floor(seconds / (3600 * 24));
            if (days < 10) {
                days = "0" + days;
            }
            seconds = seconds % (3600 * 24);
            var hours = Math.floor(seconds / 3600);
            if (hours < 10) {
                hours = "0" + hours;
            }
            seconds = seconds % 3600;
            var minutes = Math.floor(seconds / 60);
            if (minutes < 10) {
                minutes = "0" + minutes;
            }
            seconds = seconds % 60;
            if (seconds < 10) {
                seconds = "0" + seconds;
            }
            var html =
                '<div class="box"><div>' +
                days +
                "</div> <span>" +
                $("#clock").data("text-day") +
                '</span></div><div class="box"><div>' +
                hours +
                "</div> <span>" +
                $("#clock").data("text-hour") +
                '</span> </div><div class="box"><div>' +
                minutes +
                "</div> <span>" +
                $("#clock").data("text-minute") +
                '</span> </div><div class="box"><div>' +
                seconds +
                "</div> <span>" +
                $("#clock").data("text-second") +
                "</span></div>";
            $("#clock").html(html);
        }
        var time = $("#clock").data("date");
        $("#clock").countdown(time.replace(/-/g, "/"), function (event) {
            if (event.type == "stoped") {
                var together = new Date($("#clock").data("date"));
                together.setHours(0);
                together.setMinutes(0);
                together.setSeconds(0);
                together.setMilliseconds(0);
                setInterval(function () {
                    timeElapse(together);
                }, 1000);
            } else {
                var $this = $(this).html(
                    event.strftime(
                        "" +
                            '<div class="box"><div>%D</div> <span>' +
                            $("#clock").data("text-day") +
                            "</span> </div>" +
                            '<div class="box"><div>%H</div> <span>' +
                            $("#clock").data("text-hour") +
                            "</span> </div>" +
                            '<div class="box"><div>%M</div> <span>' +
                            $("#clock").data("text-minute") +
                            "</span> </div>" +
                            '<div class="box"><div>%S</div> <span>' +
                            $("#clock").data("text-second") +
                            "</span> </div>"
                    )
                );
            }
        });
    }

    if ($("#photoGalleryContainer").length) {
        var $grid = $("#photoGalleryContainer").masonry({
            itemSelector: ".gallery-item",
            columnWidth: ".gallery-item",
            percentPosition: true,
        });

        $grid.imagesLoaded().progress(function () {
            $grid.masonry("layout");
        });
    }

    $(document).on("click", ".btn-see-more-gallery", function () {
        let indexNumber = $(this).data("index") || 0;
        $(this).lightGallery({
            dynamic: true,
            dynamicEl: photoGalleries,
            download: false,
            autoplay: true,
            preload: 2,
            appendSubHtmlTo: ".lg-item",
            index: parseInt(indexNumber),
        });
    });

    $(document).on("click", ".qr-code-image", function () {
        let srcImage = $(this).attr("src");
        $(this).lightGallery({
            thumbnail: true,
            dynamic: true,
            dynamicEl: [
                {
                    src: srcImage,
                },
            ],
            download: false,
            autoplay: true,
            preload: 2,
            appendSubHtmlTo: ".lg-item",
        });
    });
});
