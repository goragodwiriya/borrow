function initBorrowIndex() {
  var tbody = $G("tb_products");

  function findInput(inputs, name) {
    var patt = new RegExp(name + "_[0-9]+"),
      l = inputs.length;
    for (var i = 0; i < l; i++) {
      if (patt.test(inputs[i].id)) {
        return inputs[i];
      }
    }
    return null;
  }

  function findInputRow(name, val) {
    var tr,
      patt = new RegExp(name + "_[0-9]+");
    forEach($G(tbody).elems("input"), function() {
      if (patt.test(this.id) && this.value == val) {
        tr = this;
        return true;
      }
    });
    return tr;
  }

  function getInput(inputs, name) {
    return findInput(inputs, name);
  }

  function setInputValue(inputs, name, value) {
    var input = findInput(inputs, name);
    if (input) {
      input.value = value;
      if (input.type.toLowerCase() == "checkbox") {
        input.checked = value > 0;
      }
    }
  }

  function initTBODY() {
    var cls,
      row = 0;
    forEach(tbody.elems("tr"), function() {
      this.id = tbody.id + "_" + row;
      forEach($G(this).elems("input"), function() {
        $G(this).id = this.name.replace(/([\[\]_]+)/g, "_") + row;
        if (this.className == "num") {
          new GMask(this, function() {
            return /^[0-9]+$/.test(this.value);
          });
          this.addEvent("change", function() {
            this.value = Math.min(this.max, this.value);
          });
        }
        this.addEvent("focus", function() {
          this.select();
        });
      });
      forEach($G(this).elems("a"), function() {
        cls = $G(this).hasClass("delete");
        if (cls == "delete") {
          callClick(this, function() {
            if (tbody.elems("tr").length > 1 && confirm(trans("You want to XXX ?").replace(/XXX/, trans("delete")))) {
              var tr = $G(this.parentNode.parentNode);
              tr.remove();
            }
          });
        }
      });
      row++;
    });
  }

  initAutoComplete(
    "equipment",
    WEB_URL + "index.php/borrow/model/autocomplete/findInventory",
    "serial,equipment",
    "product", {
      onSuccess: function() {
        var q = 'value=' + $E('equipment').value;
        send(WEB_URL + "index.php/inventory/model/inventory/find", q, function(xhr) {
          var inputs,
            input,
            ntr,
            quantity = $E("quantity").value.toInt();
          ds = xhr.responseText.toJSON();
          if (ds) {
            ntr = findInputRow("id", ds.id);
            if (ntr == null) {
              ntr = findInputRow("topic", "");
              if (ntr == null) {
                ntr = $G(tbody.firstChild).copy(false);
                tbody.appendChild(ntr);
              } else {
                ntr = ntr.parentNode.parentNode.parentNode;
              }
              var inputs = $G(ntr).elems("input");
              setInputValue(inputs, "topic", (ds.equipment + ' (' + ds.serial + ')').unentityify());
              setInputValue(inputs, "unit", ds.unit.unentityify());
              setInputValue(inputs, "id", ds.id);
              ntr.removeClass("hidden");
              input = getInput(ntr.elems("input"), "quantity");
            } else {
              ntr = $G(ntr.parentNode.parentNode);
              input = getInput(ntr.elems("input"), "quantity");
              quantity += input.value.toInt();
            }
            if (ds.stock == -1) {
              input.value = quantity;
              input.max = 2147483647;
            } else {
              input.value = Math.min(ds.stock, quantity);
              input.max = ds.stock;
            }
            initTBODY();
            $E("equipment").value = "";
            $E("quantity").value = 1;
          }
        }, this);
      },
      callBack: function() {
        $E('equipment').value = this.serial;
      }
    }
  );
  initTBODY();
  $G('borrow_date').addEvent("change", function() {
    if (this.value != "") {
      $E('return_date').calendar.minDate(this.value);
    }
  });
}

function initBorrowOrder() {
  initBorrower();
  forEach($G('tb_products').elems("a"), function() {
    callClick(this, function() {
      send(WEB_URL + "index.php/borrow/model/orderstatus/action", 'id=' + this.id, doFormSubmit, this);
    })
  });
  $G('borrow_date').addEvent("change", function() {
    if (this.value != "") {
      $E('return_date').calendar.minDate(this.value);
    }
  });
}

function initBorrower() {
  if ($E('borrower')) {
    initAutoComplete(
      "borrower",
      WEB_URL + "index.php/index/model/autocomplete/findUser",
      "name,username,phone",
      "customer", {
        get: function() {
          return "name=" + encodeURIComponent($E("borrower").value) + "&from=name,username,phone";
        },
        callBack: function() {
          $E("borrower_id").value = this.id;
          $G("borrower").valid().value = this.name.unentityify();
        },
        onChanged: function() {
          $E("borrower_id").value = 0;
          $G("borrower").reset();
        }
      }
    );
  }
}
