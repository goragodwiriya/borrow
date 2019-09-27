/**
 * Datalist
 *
 * @filesource js/datalist.js
 * @link http://www.kotchasan.com/
 * @copyright 2019 Goragod.com
 * @license http://www.kotchasan.com/license/
 */
(function() {
  "use strict";
  window.Datalist = GClass.create();
  Datalist.prototype = {
    initialize: function(text) {
      if (!$E(text)) {
        console.log("[Datalist] Cannot find target element " + text);
        return;
      }
      this.input = $G(text);
      if (this.input.getAttribute('Datalist')) {
        return;
      }
      this.input.setAttribute('Datalist', true);
      this.hidden = document.createElement("input");
      this.hidden.type = 'hidden';
      this.hidden.name = this.input.id || this.input.name;
      if (this.hidden.name == this.input.name) {
        this.input.removeAttribute('name');
      }
      this.hidden.value = this.input.value;
      this.value = null;
      this.nameValue = this.input.get('nameValue');
      if (this.nameValue === null) {
        this.nameValue = '';
        this.customText = false;
      } else {
        this.customText = true;
      }
      this.input.removeAttribute('nameValue');
      this.input.parentNode.appendChild(this.hidden);
      this.input.getValue = function() {
        return self.hidden.value;
      };
      this.input.setDatalist = function(datas) {
        var old_value = self.hidden.value;
        self.datalist = {};
        for (var key in datas) {
          self.datalist[key] = datas[key];
        }
        listindex = 0;
        self.input.value = self.datalist[old_value] || self.nameValue;
        self.hidden.value = old_value;
      };
      this.value_change = false;
      var cancelEvent = false,
        showing = false,
        listindex = 0,
        list = [],
        _list = this.input.get("list"),
        self = this;
      this.datalist = {};
      if (_list) {
        _list = $G(_list);
        forEach(_list.elems('option'), function() {
          self.datalist[this.value] = this.innerHTML;
        });
        this.input.removeAttribute('list');
        _list.remove();
      }
      this.input.value = this.datalist[this.hidden.value] || this.nameValue;
      var display = document.createElement("div");
      document.body.appendChild(display);
      $G(display).className = "gautocomplete";
      display.style.left = "-100000px";
      display.style.position = "absolute";
      display.style.display = "block";
      display.style.zIndex = 9999;

      function _movehighlight(id) {
        listindex = Math.max(0, id);
        listindex = Math.min(list.length - 1, listindex);
        var selItem = null;
        forEach(list, function() {
          if (listindex == this.itemindex) {
            this.addClass("select");
            selItem = this;
          } else {
            this.removeClass("select");
          }
        });
        return selItem;
      }

      function _onSelect() {
        if (showing) {
          _hide();
          var value = self.datalist[this.key];
          self.input.value = value;
          self.hidden.value = this.key;
          self.value_change = false;
          _doChange();
        }
      }
      var _mouseclick = function(evt) {
        _onSelect.call(this);
      };
      var _mousemove = function() {
        _movehighlight(this.itemindex);
      };

      function _populateitem(key, text) {
        var p = document.createElement('p');
        display.appendChild(p);
        p.innerHTML = text;
        $G(p).key = key;
        p.addEvent("mousedown", _mouseclick);
        p.addEvent("mousemove", _mousemove);
        p.itemindex = list.length;
        list.push(p);
      }

      function _hide() {
        display.style.left = "-100000px";
        showing = false;
      }
      var _search = function() {
        if (!cancelEvent) {
          display.innerHTML = "";
          var value,
            text = self.input.value,
            _changed = false,
            filter = new RegExp("(" + text.preg_quote() + ")", "gi");
          listindex = 0;
          list = [];
          if (self.datalist[self.hidden.value] != text) {
            self.hidden.value = '';
            self.value_change = true;
          }
          for (var key in self.datalist) {
            value = self.datalist[key];
            if (text == '') {
              _populateitem(key, value);
            } else {
              if (filter.test(value)) {
                _populateitem(key, value.replace(filter, "<em>$1</em>"));
              }
            }
          }
          _movehighlight(0);
          if (list.length > 0) {
            var vp = self.input.viewportOffset(),
              dm = self.input.getDimensions(),
              dd = display.getDimensions(),
              cw = document.viewport.getWidth();
            if (vp.left + dd.width > cw) {
              vp.left = Math.max(5, vp.left + dm.width - dd.width);
            }
            display.style.left = vp.left + "px";
            if (vp.left + dd.width > cw) {
              display.style.width = cw - vp.left - 5 + "px";
            }
            if (vp.top + dm.height + 5 + dd.height >= document.viewport.getHeight() + document.viewport.getscrollTop()) {
              display.style.top = vp.top - dd.height - 5 + "px";
            } else {
              display.style.top = vp.top + dm.height + 5 + "px";
            }
            showing = true;
          } else {
            _hide();
          }
        }
        cancelEvent = false;
      };

      function _showitem(item) {
        if (item) {
          var top = item.getTop() - display.getTop();
          var height = display.getHeight();
          if (top < display.scrollTop) {
            display.scrollTop = top;
          } else if (top >= height) {
            display.scrollTop = top - height + item.getHeight();
          }
        }
      }

      function _dokeydown(evt) {
        var key = GEvent.keyCode(evt);
        if (key == 40) {
          _showitem(_movehighlight(listindex + 1));
          cancelEvent = true;
        } else if (key == 38) {
          _showitem(_movehighlight(listindex - 1));
          cancelEvent = true;
        } else if (key == 13) {
          cancelEvent = true;
          forEach(list, function() {
            if (this.itemindex == listindex) {
              _onSelect.call(this);
            }
          });
        } else if (key == 32) {
          if (this.value == "") {
            _search();
            cancelEvent = true;
          }
        }
        if (cancelEvent) {
          GEvent.stop(evt);
        }
      }

      function _doChange() {
        var value = self.input.getValue();
        if (self.value != value) {
          self.value = value;
          window.setTimeout(function() {
            self.input.callEvent('change');
          }, 1);
        }
      }
      this.input.addEvent("click", _search);
      this.input.addEvent("keyup", _search);
      this.input.addEvent("keydown", _dokeydown);
      this.input.addEvent("change", _doChange);
      this.input.addEvent("focus", function() {
        this.select();
      });
      this.input.addEvent("blur", function() {
        if (self.value_change) {
          if (!self.customText) {
            self.input.value = '';
          } else {
            self.nameValue = self.input.value;
            self.hidden.value = '';
          }
          self.value_change = false;
        }
        _hide();
      });
      $G(document.body).addEvent("click", function(e) {
        if (GEvent.element(e) != self.input) {
          _hide();
        }
      });
      _doChange();
    }
  };
})();
