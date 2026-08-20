/*!
 * Toastify js 1.12.0
 * https://github.com/apvarun/toastify-js
 * @license MIT licensed
 *
 * Copyright (C) 2018 Varun A P
 */
(function (root, factory) {
  if (typeof module === "object" && module.exports) {
    module.exports = factory();
  } else {
    root.Toastify = factory();
  }
})(this, function (global) {
  // Object initialization
  var Toastify = function (options) {
    // Returning a new init object
    return new Toastify.lib.init(options);
  },
    // Library version
    version = "1.12.0";

  // Set the default global options
  Toastify.defaults = {
    oldestFirst: true,
    text: "Toastify is awesome!",
    node: undefined,
    duration: 3000,
    selector: undefined,
    callback: function () {
    },
    destination: undefined,
    newWindow: false,
    close: false,
    gravity: "toastify-top",
    positionLeft: false,
    position: '',
    backgroundColor: '',
    avatar: "",
    className: "",
    stopOnFocus: true,
    onClick: function () {
    },
    offset: { x: 0, y: 0 },
    escapeMarkup: true,
    ariaLive: 'polite',
    style: { background: '' }
  };

  // Defining the prototype of the object
  Toastify.lib = Toastify.prototype = {
    toastify: version,

    constructor: Toastify,

    // Initializing the object with required parameters
    init: function (options) {
      // Verifying and validating the input object
      if (!options) {
        options = {};
      }

      // Creating the options object
      this.options = {};

      this.toastElement = null;

      // Validating the options
      this.options.text = options.text || Toastify.defaults.text; // Display message
      this.options.node = options.node || Toastify.defaults.node;  // Display content as node
      this.options.duration = options.duration === 0 ? 0 : options.duration || Toastify.defaults.duration; // Display duration
      this.options.selector = options.selector || Toastify.defaults.selector; // Parent selector
      this.options.callback = options.callback || Toastify.defaults.callback; // Callback after display
      this.options.destination = options.destination || Toastify.defaults.destination; // On-click destination
      this.options.newWindow = options.newWindow || Toastify.defaults.newWindow; // Open destination in new window
      this.options.close = options.close || Toastify.defaults.close; // Show toast close icon
      this.options.gravity = options.gravity === "bottom" ? "toastify-bottom" : Toastify.defaults.gravity; // toast position - top or bottom
      this.options.positionLeft = options.positionLeft || Toastify.defaults.positionLeft; // toast position - left or right
      this.options.position = options.position || Toastify.defaults.position; // toast position - left or right
      this.options.backgroundColor = options.backgroundColor || Toastify.defaults.backgroundColor; // toast background color
      this.options.avatar = options.avatar || Toastify.defaults.avatar; // img element src - url or a path
      this.options.className = options.className || Toastify.defaults.className; // additional class names for the toast
      this.options.stopOnFocus = options.stopOnFocus === undefined ? Toastify.defaults.stopOnFocus : options.stopOnFocus; // stop timeout on focus
      this.options.onClick = options.onClick || Toastify.defaults.onClick; // Callback after click
      this.options.offset = options.offset || Toastify.defaults.offset; // toast offset
      this.options.escapeMarkup = options.escapeMarkup !== undefined ? options.escapeMarkup : Toastify.defaults.escapeMarkup;
      this.options.ariaLive = options.ariaLive || Toastify.defaults.ariaLive;
      this.options.style = options.style || Toastify.defaults.style;
      if (options.backgroundColor) {
        this.options.style.background = options.backgroundColor;
      }

      // Returning the current object for chaining functions
      return this;
    },

    // Building the DOM element
    buildToast: function () {
      // Validating if the options are defined
      if (!this.options) {
        throw "Toastify is not initialized";
      }

      // Creating the DOM object
      var divElement = document.createElement("div");
      divElement.className = "toastify on " + this.options.className;

      // Positioning toast to left or right or center
      if (!!this.options.position) {
        divElement.className += " toastify-" + this.options.position;
      } else {
        // To be depreciated in further versions
        if (this.options.positionLeft === true) {
          divElement.className += " toastify-left";
          console.warn('Property `positionLeft` will be depreciated in further versions. Please use `position` instead.')
        } else {
          // Default position
          divElement.className += " toastify-right";
        }
      }

      // Assigning gravity of element
      divElement.className += " " + this.options.gravity;

      if (this.options.backgroundColor) {
        // This is being deprecated in favor of using the style HTML DOM property
        console.warn('DEPRECATION NOTICE: "backgroundColor" is being deprecated. Please use the "style.background" property.');
      }

      // Loop through our style object and apply styles to divElement
      for (var property in this.options.style) {
        divElement.style[property] = this.options.style[property];
      }

      // Announce the toast to screen readers
      if (this.options.ariaLive) {
        divElement.setAttribute('aria-live', this.options.ariaLive)
      }

      // Adding the toast message/node
      if (this.options.node && this.options.node.nodeType === Node.ELEMENT_NODE) {
        // If we have a valid node, we insert it
        divElement.appendChild(this.options.node)
      } else {
        if (this.options.escapeMarkup) {
          divElement.innerText = this.options.text;
        } else {
          divElement.innerHTML = this.options.text;
        }

        if (this.options.avatar !== "") {
          var avatarElement = document.createElement("img");
          avatarElement.src = this.options.avatar;

          avatarElement.className = "toastify-avatar";

          if (this.options.position == "left" || this.options.positionLeft === true) {
            // Adding close icon on the left of content
            divElement.appendChild(avatarElement);
          } else {
            // Adding close icon on the right of content
            divElement.insertAdjacentElement("afterbegin", avatarElement);
          }
        }
      }

      // Adding a close icon to the toast
      if (this.options.close === true) {
        // Create a span for close element
        var closeElement = document.createElement("button");
        closeElement.type = "button";
        closeElement.setAttribute("aria-label", "Close");
        closeElement.className = "toast-close";
        closeElement.innerHTML = "&#10006;";

        // Triggering the removal of toast from DOM on close click
        closeElement.addEventListener(
          "click",
          function (event) {
            event.stopPropagation();
            this.removeElement(this.toastElement);
            window.clearTimeout(this.toastElement.timeOutValue);
          }.bind(this)
        );

        //Calculating screen width
        var width = window.innerWidth > 0 ? window.innerWidth : screen.width;

        // Adding the close icon to the toast element
        // Display on the right if screen width is less than or equal to 360px
        if ((this.options.position == "left" || this.options.positionLeft === true) && width > 360) {
          // Adding close icon on the left of content
          divElement.insertAdjacentElement("afterbegin", closeElement);
        } else {
          // Adding close icon on the right of content
          divElement.appendChild(closeElement);
        }
      }

      // Clear timeout while toast is focused
      if (this.options.stopOnFocus && this.options.duration > 0) {
        var self = this;
        // stop countdown
        divElement.addEventListener(
          "mouseover",
          function (event) {
            window.clearTimeout(divElement.timeOutValue);
          }
        )
        // add back the timeout
        divElement.addEventListener(
          "mouseleave",
          function () {
            divElement.timeOutValue = window.setTimeout(
              function () {
                // Remove the toast from DOM
                self.removeElement(divElement);
              },
              self.options.duration
            )
          }
        )
      }

      // Adding an on-click destination path
      if (typeof this.options.destination !== "undefined") {
        divElement.addEventListener(
          "click",
          function (event) {
            event.stopPropagation();
            if (this.options.newWindow === true) {
              window.open(this.options.destination, "_blank");
            } else {
              window.location = this.options.destination;
            }
          }.bind(this)
        );
      }

      if (typeof this.options.onClick === "function" && typeof this.options.destination === "undefined") {
        divElement.addEventListener(
          "click",
          function (event) {
            event.stopPropagation();
            this.options.onClick();
          }.bind(this)
        );
      }

      // Adding offset
      if (typeof this.options.offset === "object") {

        var x = getAxisOffsetAValue("x", this.options);
        var y = getAxisOffsetAValue("y", this.options);

        var xOffset = this.options.position == "left" ? x : "-" + x;
        var yOffset = this.options.gravity == "toastify-top" ? y : "-" + y;

        divElement.style.transform = "translate(" + xOffset + "," + yOffset + ")";

      }

      // Returning the generated element
      return divElement;
    },

    // Displaying the toast
    showToast: function () {
      // Creating the DOM object for the toast
      this.toastElement = this.buildToast();

      // Getting the root element to with the toast needs to be added
      var rootElement;
      if (typeof this.options.selector === "string") {
        rootElement = document.getElementById(this.options.selector);
      } else if (this.options.selector instanceof HTMLElement || (typeof ShadowRoot !== 'undefined' && this.options.selector instanceof ShadowRoot)) {
        rootElement = this.options.selector;
      } else {
        rootElement = document.body;
      }

      // Validating if root element is present in DOM
      if (!rootElement) {
        throw "Root element is not defined";
      }

      // Adding the DOM element
      var elementToInsert = Toastify.defaults.oldestFirst ? rootElement.firstChild : rootElement.lastChild;
      rootElement.insertBefore(this.toastElement, elementToInsert);

      // Repositioning the toasts in case multiple toasts are present
      Toastify.reposition();

      if (this.options.duration > 0) {
        this.toastElement.timeOutValue = window.setTimeout(
          function () {
            // Remove the toast from DOM
            this.removeElement(this.toastElement);
          }.bind(this),
          this.options.duration
        ); // Binding `this` for function invocation
      }

      // Supporting function chaining
      return this;
    },

    hideToast: function () {
      if (this.toastElement.timeOutValue) {
        clearTimeout(this.toastElement.timeOutValue);
      }
      this.removeElement(this.toastElement);
    },

    // Removing the element from the DOM
    removeElement: function (toastElement) {
      // Hiding the element
      // toastElement.classList.remove("on");
      // 改为添加 off 类，触发明确的退出动画
      toastElement.classList.add("off");

      // Removing the element from DOM after transition end
      window.setTimeout(
        function () {
          // remove options node if any
          if (this.options.node && this.options.node.parentNode) {
            this.options.node.parentNode.removeChild(this.options.node);
          }

          // Remove the element from the DOM, only when the parent node was not removed before.
          if (toastElement.parentNode) {
            toastElement.parentNode.removeChild(toastElement);
          }

          // Calling the callback function
          this.options.callback.call(toastElement);

          // Repositioning the toasts again
          Toastify.reposition();
        }.bind(this),
        400
      ); // Binding `this` for function invocation
    },
  };

  // Positioning the toasts on the DOM
  Toastify.reposition = function () {

    // Top margins with gravity
    var topLeftOffsetSize = {
      top: 15,
      bottom: 15,
    };
    var topRightOffsetSize = {
      top: 15,
      bottom: 15,
    };
    var offsetSize = {
      top: 15,
      bottom: 15,
    };

    // Get all toast messages on the DOM
    var allToasts = document.getElementsByClassName("toastify");

    var classUsed;

    // Modifying the position of each toast element
    for (var i = 0; i < allToasts.length; i++) {
      // Getting the applied gravity
      if (containsClass(allToasts[i], "toastify-top") === true) {
        classUsed = "toastify-top";
      } else {
        classUsed = "toastify-bottom";
      }

      var height = allToasts[i].offsetHeight;
      classUsed = classUsed.substr(9, classUsed.length - 1)
      // Spacing between toasts
      var offset = 15;

      var width = window.innerWidth > 0 ? window.innerWidth : screen.width;

      // Show toast in center if screen with less than or equal to 360px
      if (width <= 360) {
        // Setting the position
        allToasts[i].style[classUsed] = offsetSize[classUsed] + "px";

        offsetSize[classUsed] += height + offset;
      } else {
        if (containsClass(allToasts[i], "toastify-left") === true) {
          // Setting the position
          allToasts[i].style[classUsed] = topLeftOffsetSize[classUsed] + "px";

          topLeftOffsetSize[classUsed] += height + offset;
        } else {
          // Setting the position
          allToasts[i].style[classUsed] = topRightOffsetSize[classUsed] + "px";

          topRightOffsetSize[classUsed] += height + offset;
        }
      }
    }

    // Supporting function chaining
    return this;
  };

  // Helper function to get offset.
  function getAxisOffsetAValue(axis, options) {

    if (options.offset[axis]) {
      if (isNaN(options.offset[axis])) {
        return options.offset[axis];
      }
      else {
        return options.offset[axis] + 'px';
      }
    }

    return '0px';

  }

  function containsClass(elem, yourClass) {
    if (!elem || typeof yourClass !== "string") {
      return false;
    } else if (
      elem.className &&
      elem.className
        .trim()
        .split(/\s+/gi)
        .indexOf(yourClass) > -1
    ) {
      return true;
    } else {
      return false;
    }
  }

  // Setting up the prototype for the init object
  Toastify.lib.init.prototype = Toastify.lib;

  // ========== 自定义场景封装 ==========
  // 为常见的 Toast 场景提供预设配置
  Toastify.showScenario = function (type, options) {
    options = options || {};

    // 场景配置
    const scenarios = {
      'success': {
        icon: 'badge-check', // 尝试徽章对号样式，看起来更高级
        bgColor: '#10b981',
        defaultMsg: '操作成功'
      },
      'error': {
        icon: 'badge-x', // badge-x 风格统一
        bgColor: '#ef4444',
        defaultMsg: '操作失败'
      },
      'warning': {
        icon: 'badge-alert', // 与 success 的 badge-check 保持一致，风格更统一
        bgColor: '#f59e0b',
        defaultMsg: '警告提示'
      },
      'system': {
        icon: 'badge-info', // 统一使用 badge 风格
        bgColor: '#3b82f6', // blue-500
        defaultMsg: '系统通知：已自动保存更改'
      },
      'info': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '提示信息'
      },
      'loading': {
        icon: 'loader-2',
        bgColor: '#0f172a',
        defaultMsg: '正在加载...',
        animate: true,
        duration: -1
      },
      'top-center': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '顶部居中通知',
        gravity: 'top',
        position: 'center'
      },
      'bottom-center': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '底部居中通知',
        gravity: 'bottom',
        position: 'center'
      },
      'top-right': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '右上角通知',
        gravity: 'top',
        position: 'right'
      },
      'top-left': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '左上角通知',
        gravity: 'top',
        position: 'left'
      },
      'bottom-right': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '右下角通知',
        gravity: 'bottom',
        position: 'right'
      },
      'bottom-left': {
        icon: 'bell',
        bgColor: '#0f172a',
        defaultMsg: '左下角通知',
        gravity: 'bottom',
        position: 'left'
      },
      'gradient': {
        icon: 'gift',
        defaultMsg: '精彩内容',
        gradient: 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)'
      },
      'avatar': {
        image: 'https://i.pravatar.cc/100?img=33', // 默认头像
        bgColor: '#ffffff',
        defaultMsg: '<div style="display:flex;flex-direction:column;align-items:flex-start;line-height:1.2;"><span style="font-weight:700;font-size:13px;">Alice</span><span style="font-size:12px;opacity:0.8;">今晚有空一起吃饭吗？</span></div>',
        style: { color: '#1e293b', border: '1px solid #e2e8f0', boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.1)' }
      },
      'wifi-on': {
        icon: 'wifi',
        bgColor: '#10b981',
        defaultMsg: '网络已连接'
      },
      'wifi-off': {
        icon: 'wifi-off',
        bgColor: '#64748b',
        defaultMsg: '网络已断开'
      },
      'undo': {
        icon: 'trash-2',
        bgColor: '#0f172a',
        defaultMsg: '文件已删除 <button id="undo-btn" style="margin-left:8px; background:rgba(255,255,255,0.2); border:none; color:white; padding:2px 8px; border-radius:4px; font-size:12px; cursor:pointer; transition:all 0.2s;" onclick="event.stopPropagation(); this.innerText=\'已恢复\'; this.style.background=\'rgba(16, 185, 129, 0.4)\'; this.disabled=true; this.style.cursor=\'default\';">撤销</button>',
        duration: 5000
      }
    };

    const config = scenarios[type] || scenarios['info'];
    const msg = options.text || config.defaultMsg;

    // 构建视觉元素 (图标或图片)
    let mainVisual = '';
    const iconClass = config.animate ? 'animate-spin-soft' : '';

    if (options.avatar || config.image) {
      const imgSrc = options.avatar || config.image;
      mainVisual = `<img src="${imgSrc}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">`;
    } else {
      mainVisual = `<i data-lucide="${options.icon || config.icon || 'bell'}" class="${iconClass}" style="width:18px;height:18px;stroke-width:1.5px;"></i>`;
    }

    // 构建完整的 Toast 内容
    const toastContent = `<div class="toast-content">${mainVisual}<span>${msg}</span></div>`;

    // 合并配置（不包含 onClick）
    const toastConfig = {
      text: toastContent,
      duration: config.duration !== undefined ? config.duration : (options.duration !== undefined ? options.duration : 3000),
      gravity: config.gravity || options.gravity || 'top',
      position: config.position || options.position || 'center',
      close: false,
      escapeMarkup: false,
      stopOnFocus: true,
      style: {
        background: config.gradient || config.bgColor || '#0f172a',
        borderRadius: '200px',
        boxShadow: '0 20px 40px -10px rgba(0, 0, 0, 0.4)',
        fontSize: '14px',
        fontWeight: '500',
        color: '#ffffff',
        ...(config.style || {}),
        ...(options.style || {})
      }
    };

    // 创建并显示 Toast 实例
    const instance = Toastify(toastConfig);
    instance.showToast();

    // 使用闭包捕获实例，添加点击关闭功能
    setTimeout(function () {
      if (instance.toastElement) {
        instance.toastElement.addEventListener('click', function (e) {
          // 执行自定义回调
          if (options.onClick) {
            options.onClick.call(instance);
          }
          // 点击即关闭
          instance.hideToast();
        });
      }
    }, 50);

    // 延迟初始化 Lucide 图标
    setTimeout(function () {
      if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
      }
    }, 10);

    return instance;
  };

  // Returning the Toastify function to be assigned to the window object/module
  return Toastify;
});

