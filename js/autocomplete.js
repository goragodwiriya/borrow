/**
 * GAutoComplete
 *
 * @filesource js/autocomplete.js
 * @link http://www.kotchasan.com/
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 */
(function() {
  "use strict";
  window.GAutoComplete = GClass.create();
  GAutoComplete.prototype = {
    initialize: function(id, o) {
      var options = {
        callBack: $K.emptyFunction,
        get: $K.emptyFunction,
        populate: $K.emptyFunction,
        onSuccess: $K.emptyFunction,
        onChanged: $K.emptyFunction,
        url: false,
        interval: 300
      };
      for (var property in o) {
        options[property] = o[property];
      }
      var cancelEvent = false,
        showing = false,
        listindex = 0,
        list = [],
        req = new GAjax(),
        self = this;
      this.input = $G(id);
      this.text = this.input.value;
      if (!$E("gautocomplete_div")) {
        var div = document.createElement("div");
        document.body.appendChild(div);
        div.id = "gautocomplete_div";
      }
      var display = $G("gautocomplete_div");
      display.className = "gautocomplete";
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
          try {
            self.input.datas = this.datas;
            options.callBack.call(this.datas);
            self.text = self.input.value;
          } catch (e) {}
        }
      }
      var _mouseclick = function() {
        _onSelect.call(this);
        if (Object.isFunction(options.onSuccess)) {
          options.onSuccess.call(self.input);
        }
      };
      var _mousemove = function() {
        _movehighlight(this.itemindex);
      };

      function _populateitems(datas) {
        display.innerHTML = "";
        list = [];
        var f, i, r, p;
        for (i in datas) {
          r = options.populate.call(datas[i]);
          if (r && r != "") {
            p = r.toDOM();
            f = p.firstChild;
            f.datas = datas[i];
            $G(f).addEvent("mousedown", _mouseclick);
            f.addEvent("mousemove", _mousemove);
            f.itemindex = list.length;
            list.push(f);
            display.appendChild(p);
          }
        }
        _movehighlight(0);
      }

      function _hide() {
        self.input.removeClass("wait");
        display.style.left = "-100000px";
        showing = false;
      }
      var _search = function() {
        window.clearTimeout(self.timer);
        req.abort();
        if (self.text != self.input.value) {
          options.onChanged.call(self.input);
        }
        if (!cancelEvent && options.url) {
          var q = options.get.call(this);
          if (q && q != "") {
            self.input.addClass("wait");
            self.timer = window.setTimeout(function() {
              req.send(options.url, q, function(xhr) {
                self.input.removeClass("wait");
                if (xhr.responseText !== "") {
                  var datas = xhr.responseText.toJSON();
                  listindex = 0;
                  if (datas) {
                    _populateitems(datas);
                  } else {
                    display.setValue(xhr.responseText);
                  }
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
              });
            }, options.interval);
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
          this.removeClass("wait");
          forEach(list, function() {
            if (this.itemindex == listindex) {
              _onSelect.call(this);
            }
          });
          if (Object.isFunction(options.onSuccess)) {
            options.onSuccess.call(self.input);
          }
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
      this.input.addEvent("click", _search);
      this.input.addEvent("keyup", _search);
      this.input.addEvent("keydown", _dokeydown);
      this.input.addEvent("blur", function() {
        _hide();
      });
      $G(document.body).addEvent("click", function() {
        _hide();
      });
    },
    setText: function(value) {
      this.input.value = value;
      this.text = value;
    },
    valid: function() {
      this.input.valid();
      this.text = this.input.value;
    },
    invalid: function() {
      this.input.invalid();
      this.text = this.input.value;
    },
    reset: function() {
      this.input.reset();
      this.text = this.input.value;
    }
  };
})();

function initAutoComplete(id, link, displayFields, icon, options) {
  var obj,
    df = displayFields.split(",");

  function doGetQuery() {
    var q = null,
      value = $E(id).value;
    if (value != "") {
      q = id + "=" + encodeURIComponent(value);
    }
    return q;
  }

  function doCallBack() {
    for (var prop in this) {
      $G(prop).setValue(this[prop] === null ? "" : this[prop]);
    }
    obj.valid();
  }

  function doPopulate() {
    if ($E(id)) {
      var datas = new Array();
      for (var i in df) {
        if (this[df[i]] !== null && this[df[i]] != "") {
          datas.push(this[df[i]]);
        }
      }
      var row = datas.join(" ").unentityify();
      forEach($E(id).value.replace(/[\s]+/, " ").split(" "), function() {
        if (this.length > 0) {
          var patt = new RegExp("(" + this.preg_quote() + ")", "gi");
          row = row.replace(patt, "<em>$1</em>");
        }
      });
      return '<p><span class="icon-' + (icon || this.icon || "search") + '">' + row + "</span></p>";
    }
  }
  var o = {
    get: doGetQuery,
    populate: doPopulate,
    callBack: doCallBack,
    url: link
  };
  for (var prop in options) {
    o[prop] = options[prop];
  }
  obj = new GAutoComplete(id, o);
  return obj;
}