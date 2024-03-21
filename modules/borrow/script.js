function initBorrowIndex() {
  var tbody = $G('tb_products');

  function findInput(inputs, name) {
    var patt = new RegExp('^' + name + '_[0-9]+'),
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
      patt = new RegExp('^' + name + '_[0-9]+');
    forEach($G(tbody).elems('input'), function() {
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
      if (input.type.toLowerCase() == 'checkbox') {
        input.checked = value > 0;
      }
    }
  }

  function initTBODY() {
    var cls,
      row = 0;
    forEach(tbody.elems('tr'), function() {
      this.id = tbody.id + '_' + row;
      forEach($G(this).elems('input'), function() {
        $G(this).id = this.name.replace(/([\[\]_]+)/g, '_') + row;
        if (this.className == 'num') {
          new GMask(this, function() {
            return /^[0-9]+$/.test(this.value);
          });
          this.addEvent('change', function() {
            this.value = Math.min(this.max, this.value);
          });
        }
        this.addEvent('focus', function() {
          this.select();
        });
      });
      forEach($G(this).elems('a'), function() {
        cls = $G(this).hasClass('delete');
        if (cls == 'delete') {
          callClick(this, function() {
            if (confirm(trans('You want to XXX ?').replace(/XXX/, trans('delete')))) {
              var trs = tbody.elems('tr');
              if (trs.length > 1) {
                var tr = $G(this.parentNode.parentNode);
                tr.remove();
              } else if (trs.length == 1) {
                var inputs = $G(trs[0]).elems('input');
                setInputValue(inputs, 'topic', '');
                setInputValue(inputs, 'product_no', '');
                setInputValue(inputs, 'quantity', 0);
                trs[0].addClass('hidden');
              }
            }
          });
        }
      });
      row++;
    });
  }

  if (true) {
    initAutoComplete(
      'inventory',
      WEB_URL + 'index.php/borrow/model/autocomplete/findInventory',
      'product_no,topic',
      'find', {
        onSuccess: function() {
          send(WEB_URL + 'index.php/borrow/model/inventory/find', 'value=' + encodeURIComponent($E('inventory').value), function(xhr) {
            var inputs,
              input,
              ntr;
            ds = xhr.responseText.toJSON();
            if (ds) {
              var inputs,
                ntr = findInputRow('product_no', ds.product_no),
                quantity = $E('inventory_quantity').value.toInt();
              if (ntr == null) {
                ntr = findInputRow('topic', '');
                if (ntr == null) {
                  ntr = $G(tbody.firstChild).copy(false);
                  tbody.appendChild(ntr);
                } else {
                  ntr = ntr.parentNode.parentNode.parentNode;
                }
                var inputs = $G(ntr).elems('input');
                setInputValue(inputs, 'topic', ds.topic.unentityify());
                setInputValue(inputs, 'unit', ds.unit.unentityify());
                setInputValue(inputs, 'product_no', ds.product_no.unentityify());
                ntr.removeClass('hidden');
                input = getInput(ntr.elems('input'), 'quantity');
              } else {
                ntr = $G(ntr.parentNode.parentNode.parentNode);
                input = getInput(ntr.elems('input'), 'quantity');
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
              $E('inventory').value = '';
              $E('inventory_quantity').value = 1;
            } else if (xhr.responseText != '') {
              console.log(xhr.responseText);
            } else {
              alert(SORRY_XXX_NOT_FOUND.replace(/XXX/, $E('inventory').title));
              $G('inventory').invalid();
            }
          }, this);
        },
        callBack: function() {
          $E('inventory').value = this.product_no;
        }
      }
    );
  }
  initTBODY();
  $G('borrow_date').addEvent('change', function() {
    if (this.value != '') {
      $E('return_date').min = this.value;
    }
  });
}

function initBorrowOrder() {
  forEach($G('tb_products').elems('a'), function() {
    callClick(this, function() {
      send(WEB_URL + 'index.php/borrow/model/orderstatus/action', 'id=' + this.id, doFormSubmit, this);
    })
  });
  $G('borrow_date').addEvent('change', function() {
    if (this.value != '') {
      $E('return_date').min = this.value;
    }
  });
  initBorrower();
}

function initBorrower() {
  if ($E('borrower')) {
    initAutoComplete(
      'borrower',
      WEB_URL + 'index.php/index/model/autocomplete/findUser',
      'name,username,phone',
      'customer', {
        get: function() {
          return 'name=' + encodeURIComponent($E('borrower').value) + '&from=name,username,phone';
        },
        callBack: function() {
          $E('borrower_id').value = this.id;
          $G('borrower').valid().value = this.name.unentityify();
        },
        onChanged: function() {
          $E('borrower_id').value = 0;
          $G('borrower').reset();
        }
      }
    );
  }
}
