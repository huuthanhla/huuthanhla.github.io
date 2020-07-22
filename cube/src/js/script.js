import { select } from './utilities'

class Cube {
  constructor(selector, {
    openClass = 'is-open',
    closeClass = 'is-close',
    time = 1000
  } = {}) {
    this.el = select(selector);
    this.settings = {
      openClass,
      closeClass,
      time
    };
    this._init();
  }

  _init() {
    this._initData();
    this._initEvents();
  }

  _initData() {
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
  }

  _initEvents() {
    this.callbacks.onDrag = this.drag.bind(this);
    this.callbacks.onRelease = this.relase.bind(this);
    this.callbacks.dblClick = this.opening.bind(this);

    this.el.addEventListener('mousedown', (event) => {
      event.preventDefault();
      this.currentX = event.clientX;
      this.currentY = event.clientY;
      this.rotateX = Number(this.el.style.transform.match(/rotateX\((-?[0-9]+(\.[0-9])?)*deg\)/)[1]);
      this.rotateY = Number(this.el.style.transform.match(/rotateY\((-?[0-9]+(\.[0-9])?)*deg\)/)[1]);
      if (!this.state) {
        document.addEventListener('mousemove', this.callbacks.onDrag, false);
        document.addEventListener('mouseup', this.callbacks.onRelease, false);
      }
    });
    this.el.addEventListener('dblclick', this.callbacks.dblClick, false);
  }

  drag(event) {
    let dragX = (event.clientX - this.currentX);
    let dragY = (event.clientY - this.currentY);
    if (event.buttons === 1) {
      this.el.style.transform = `rotateX(${this.rotateX - (dragY / 2)}deg) rotateY(${this.rotateY + (dragX / 2)}deg)`;
    }
    if (event.buttons === 1 && this.light) {
      this.light.style.transform = `rotateX(${(this.rotateX - (dragY / 2)) * -1}deg) rotateY(${(this.rotateY + (dragX / 2)) * -1}deg)`;
    }
  }

  relase(event) {
    event.preventDefault();
    document.removeEventListener('mousemove', this.callbacks.onDrag);
    document.removeEventListener('mouseup', this.callbacks.onRelease);
  }

  opening(event) {
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
    setTimeout(() => {
      this.el.classList.add(this.settings.closeClass);
      this.wrapper.classList.remove('is-open');
    }, 1);
    setTimeout(() => {
      this.el.style.transition = '0s';
      this.el.classList.remove(this.settings.closeClass);
    }, this.settings.time);
  }
}

export default Cube;
