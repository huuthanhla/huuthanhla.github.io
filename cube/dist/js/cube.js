(function (global, factory) {
	typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
	typeof define === 'function' && define.amd ? define(factory) :
	(global.Cube = factory());
}(this, (function () { 'use strict';

/**
 * Utilities
 */
function select(element) {
  if (typeof element === 'string') {
    return document.querySelector(element);
  }
  return element;
}















/**
   * Converts an array-like object to an array.
   */

var Cube = function Cube(selector, ref) {
  if ( ref === void 0 ) ref = {};
  var openClass = ref.openClass; if ( openClass === void 0 ) openClass = 'is-open';
  var closeClass = ref.closeClass; if ( closeClass === void 0 ) closeClass = 'is-close';
  var time = ref.time; if ( time === void 0 ) time = 1000;

  this.el = select(selector);
  this.settings = {
    openClass: openClass,
    closeClass: closeClass,
    time: time
  };
  this._init();
};

Cube.prototype._init = function _init () {
  this._initData();
  this._initEvents();
};

Cube.prototype._initData = function _initData () {
  this.light = this.el.querySelector('.cube-light');
  this.wrapper = this.el.parentNode;
  this.currentX = 0;
  this.currentY = 0;
  this.rotateX = 0;
  this.rotateY = 0;
  this.callbacks = {};
  this.state = false;

  this.el.style.transform = 'translate3d(0, 0, 0) rotateX(-20deg) rotateY(45deg)';
  if (this.light) this.light.style.transform = 'rotateX(20deg) rotateY(-45deg)';
};

Cube.prototype._initEvents = function _initEvents () {
    var this$1 = this;

  this.callbacks.onDrag = this.drag.bind(this);
  this.callbacks.onRelease = this.relase.bind(this);
  this.callbacks.dblClick = this.opening.bind(this);

  this.el.addEventListener('mousedown', function (event) {
    event.preventDefault();
    this$1.currentX = event.clientX;
    this$1.currentY = event.clientY;
    this$1.rotateX = Number(this$1.el.style.transform.match(/rotateX\((-?[0-9]+(\.[0-9])?)*deg\)/)[1]);
    this$1.rotateY = Number(this$1.el.style.transform.match(/rotateY\((-?[0-9]+(\.[0-9])?)*deg\)/)[1]);
    if (!this$1.state) {
      document.addEventListener('mousemove', this$1.callbacks.onDrag, false);
      document.addEventListener('mouseup', this$1.callbacks.onRelease, false);
    }
  });
  this.el.addEventListener('dblclick', this.callbacks.dblClick, false);
};

Cube.prototype.drag = function drag (event) {
  var dragX = (event.clientX - this.currentX);
  var dragY = (event.clientY - this.currentY);
  if (event.buttons === 1) {
    this.el.style.transform = "rotateX(" + (this.rotateX - (dragY / 2)) + "deg) rotateY(" + (this.rotateY + (dragX / 2)) + "deg)";
  }
  if (event.buttons === 1 && this.light) {
    this.light.style.transform = "rotateX(" + ((this.rotateX - (dragY / 2)) * -1) + "deg) rotateY(" + ((this.rotateY + (dragX / 2)) * -1) + "deg)";
  }
};

Cube.prototype.relase = function relase (event) {
  event.preventDefault();
  document.removeEventListener('mousemove', this.callbacks.onDrag);
  document.removeEventListener('mouseup', this.callbacks.onRelease);
};

Cube.prototype.opening = function opening (event) {
    var this$1 = this;

  if (!this.state) {
    this.state = true;
    this.el.style.transition = '1s';
    this.el.classList.remove(this.settings.closeClass);
    this.el.classList.add(this.settings.openClass);
    this.wrapper.classList.add('is-open');
    return;
  }
  this.state = false;
  this.el.classList.remove(this.settings.openClass);
  setTimeout(function () {
    this$1.el.classList.add(this$1.settings.closeClass);
    this$1.wrapper.classList.remove('is-open');
  }, 1);
  setTimeout(function () {
    this$1.el.style.transition = '0s';
    this$1.el.classList.remove(this$1.settings.closeClass);
  }, this.settings.time);
};

return Cube;

})));
