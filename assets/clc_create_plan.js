(function ($) {

  Drupal.clcCreatePlan = {};
  Drupal.clcCreatePlan.timestamp = 0;
  Drupal.clcCreatePlan.productSelected = 'product';
  Drupal.clcCreatePlan.specialPackSelected = [];
  Drupal.clcCreatePlan.orderPrice = {};
  Drupal.clcCreatePlan.orderPrice.product = 0;
  Drupal.clcCreatePlan.orderPrice.special = 0;
  Drupal.clcCreatePlan.orderPrice.pack = 0;
  var classLabel = 'label-disabled';

  Drupal.behaviors.clcCreatePlan = {
    attach: function (context, settings) {
      // Disable container block initialy.
      $('.container-disabled input').prop('disabled', true);
      $('#edit-specials .duration input').prop('disabled', true);

      // Disable and Enable container block.
      $('.container-block legend').click(function (e) {
        if (Drupal.ValidateOnceTime(e.timeStamp)) {
          let $parent = $(this).parents('.container-block');
          // Initialize variables.
          Drupal.clcCreatePlan.orderPrice.product = 0;
          Drupal.clcCreatePlan.orderPrice.special = 0;
          Drupal.clcCreatePlan.orderPrice.pack = 0;
          Drupal.clcCreatePlan.specialPackSelected = [];

          // Disable container block.
          let $containers_to_disable = $('.container-block').not($parent);
          $containers_to_disable.addClass('container-disabled');
          $containers_to_disable.find('input').prop('disabled', true);

          // Active the current container block.
          $parent.find('input').prop('disabled', false);
          $parent.removeClass('container-disabled');

          // Move the special packages block.
          let $special_block = $('#edit-specials');
          $special_block.find('input').prop('checked', false);
          // Inactivate duration.
          $('.container-inst-face-what input.form-radio').prop('checked', false);
          $('.container-inst-face-what input.form-radio').prop('disabled', true);
          $('.container-inst-face-what input.check-inst-face-what').attr('packageid', 'Arm_Paq_especial_3');
          $parent.after($special_block);

          // Disable and Enable container package.
          let $parent_pack = $(this).parents('fieldset.container-package');
          if ($parent_pack.length) {
            // Disable container package.
            let $containers_to_disable = $('fieldset.container-package').not($parent_pack);
            $containers_to_disable.addClass('container-pk-disabled');
            $containers_to_disable.find('input').prop('disabled', true).attr('checked', false);

            // Active the current container package.
            let $radios = $parent_pack.find('input.form-radio');
            let $first_radio = $radios.first();
            $radios.prop('disabled', false);
            $first_radio.attr('checked', true);
            $parent_pack.removeClass('container-pk-disabled');

            let pack_id = $first_radio.val();
            Drupal.getPricePackage(pack_id);
            Drupal.clcCreatePlan.productSelected = 'package';
          }
          else {
            let minut_selected = $('#edit-minuts .form-radio:checked').val();
            let internet_selected = $('#edit-internet .form-radio:checked').val();

            Drupal.clcCreatePlan.productSelected = 'product';
            Drupal.getInternetByProduct(minut_selected);
            Drupal.getPriceProductAjax(minut_selected, internet_selected);
          }
        }
      });

      // Disable default Internet and Search value product default.
      if ($('#edit-minuts .form-radio:checked') && $('#edit-internet .form-radio:checked')) {
        let minut_selected = $('#edit-minuts .form-radio:checked').val();
        let internet_selected = $('#edit-internet .form-radio:checked').val();

        Drupal.getInternetByProduct(minut_selected);
        Drupal.getPriceProductAjax(minut_selected, internet_selected);
      }

      // To search the internet available by minuts.
      $('#edit-minuts .form-radio').change(function (e) {
        if (Drupal.ValidateOnceTime(e.timeStamp)) {
          let minut_selected = $(this).val();
          Drupal.getInternetByProduct(minut_selected);
        }
      });

      // To search the price product for internet selected.
      $('#edit-internet .form-radio').change(function () {
        let minut_selected = $('#edit-minuts .form-radio:checked').val();
        let internet_selected = $(this).val();

        Drupal.getPriceProductAjax(minut_selected, internet_selected);
      });

      // Inactivate duration.
      $('.container-inst-face-what input.form-radio').prop('checked', false);
      $('.container-inst-face-what input.form-radio').prop('disabled', true);
      // Handle to special packages.
      $('#edit-specials input').change(function (e) {
        if (Drupal.ValidateOnceTime(e.timeStamp)) {
          // To enable and disable duration of special packages.
          if ($(this).hasClass('check-inst-face-what')) {
            // Checkbox is checked.
            if ($(this).prop('checked')) {
              // Activate duration.
              $('.container-inst-face-what input.form-radio').prop('disabled', false);
              $('.container-inst-face-what input.form-radio').last().prop('checked', true);
            }
            else {
              // Inactivate duration.
              $('.container-inst-face-what input.form-radio').prop('checked', false);
              $('.container-inst-face-what input.form-radio').prop('disabled', true);
              $('.container-inst-face-what input.check-inst-face-what').attr('packageid', 'Arm_Paq_especial_3');
            }
          }

          // Change package ID to inst-face-what for duration selected.
          if ($(this).hasClass('form-radio')) {
            let package_id = $(this).val();
            $('.container-inst-face-what input.check-inst-face-what').attr('packageid', package_id);
          }

          // Initalize variable packs special.
          Drupal.clcCreatePlan.orderPrice.special = 0;
          Drupal.clcCreatePlan.specialPackSelected = {};
          let $checks_actived = $('#edit-specials .form-checkbox:checked');
          let packages = [];
          $.each($checks_actived, function (key, value) {
            let package_id = $(this).attr('packageid');
            let package_name = $(this).next().text();

            // Packages availables.
            packages[key] = package_id;
            // Group package information by Special Package.
            Drupal.clcCreatePlan.specialPackSelected[key] = {};
            Drupal.clcCreatePlan.specialPackSelected[key].pack_id = package_id;
            Drupal.clcCreatePlan.specialPackSelected[key].pack_name = package_name;
            Drupal.clcCreatePlan.specialPackSelected[key].pack_duration = '30 días';

            if (package_id == 'Arm_Paq_especial_2') {
              Drupal.clcCreatePlan.specialPackSelected[key].pack_duration = '3 días';
            }
          });

          if (packages.length) {
            // Execute the ajax functionallity.
            $.ajax({
              url: "/ajax-get-special-price",
              type: "POST",
              data: {
                'packs': packages,
              },
              dataType: "json",
              success: function (data, textStatus, jqXHR) {
                // Set value of order.
                Drupal.clcCreatePlan.orderPrice.special = data;
                Drupal.SetOrderPrice();
              }
            });
          }
          else {
            Drupal.SetOrderPrice();
          }
        }
      });

      $('#edit-packages input.form-radio').change(function (e) {
        if (Drupal.ValidateOnceTime(e.timeStamp)) {
          let pack_id = $(this).val();

          Drupal.getPricePackage(pack_id);
        }
      });

      var page = $(".page-paquetes-y-beneficios");
      if (page.length > 0) {
        var contentWidget = $('<div>', {
          id: 'contentWidget',
          class: 'contentWidget'
        });
        var closer = $('<div>', {
          id: 'closer',
          class: 'closer'
        });
        var access = $('<div>', {
          id: 'access',
          class: 'access'
        });
        $("#extras").append(contentWidget);
        $("#contentWidget").append($("#edit-total").clone());
        if ($(window).width() > 480) {
          $("#contentWidget").append(closer);
          $("#contentWidget").append(access);
          $("#access").hide();

          $("#closer").click(
            () => {
              $("#extras").find("#edit-total").hide(100);
              $("#closer").hide(100);
              $("#access").show(100);
            }
          );
          $("#access").click(
            function () {
              $("#extras").find("#edit-total").show(100);
              $("#closer").show(100);
              $(".access").hide();
            }
          );
        }

        if ($(window).width() < 480) {
          $("#contentWidget").addClass("mobile");
        }
      }



      // Event to show the purchase summary.
      $('.goto-summary').click(function (e) {
        e.preventDefault();
        if (Drupal.ValidateOnceTime(e.timeStamp)) {
          let productSelected = Drupal.clcCreatePlan.productSelected;
          let $summary = $('#edit-summary');
          let package_selected = false;

          // Add class to hide purchase summary.
          $('.field-summary').addClass('field-hidden');

          if (productSelected == 'product') {
            // Get value selected.
            let minut_selected = $('#edit-minuts .form-radio:checked').val();
            let internet_selected = $('#edit-internet .form-radio:checked').val();
            let internet_selected_text = $('#edit-internet .form-radio:checked').next().text();

            // Print the information selected.
            if (minut_selected > 10000) {
              $summary.find('.minuts-info .value').text('Minutos ILIMITADOS hasta por 30 días');
            }
            else {
              $summary.find('.minuts-info .value').text(minut_selected + ' minutos hasta por 30 días');
            }
            $summary.find('.data-info .value').text(internet_selected_text + ' hasta por 30 días');

            // Remove class to hidde purchase summary.
            $('.field-summary.minuts-info').removeClass('field-hidden');
            $('.field-summary.data-info').removeClass('field-hidden');

            // Execute the ajax functionallity.
            $.ajax({
              url: "/ajax-get-product-package-id",
              type: "POST",
              data: {
                'minut_selected': minut_selected,
                'internet_selected': internet_selected,
              },
              dataType: "json",
              success: function (data, textStatus, jqXHR) {
                package_selected = data;
              }
            });
          }
          else if (productSelected == 'package') {
            // Get value selected.
            let $parent_pack = $('#edit-packages .form-radio:checked').parent();
            let package_selected_text = $('#edit-packages .form-radio:checked').next().text();
            package_selected = $('#edit-packages .form-radio:checked').val();

            if ($parent_pack.hasClass('form-item-packages-internet')) {
              $summary.find('.plan-info .summary-label').text('Solo datos:');
            }
            else {
              $summary.find('.plan-info .summary-label').text('Solo minutos:');
            }

            // Print the information selected.
            $summary.find('.plan-info .value').text(package_selected_text);
            $('.field-summary.plan-info').removeClass('field-hidden');
          }

          let interval = setInterval(function () {
            if (package_selected != false) {
              // Special packages.
              let specialPackages = Drupal.clcCreatePlan.specialPackSelected;
              let special_packages_selected = '';
              if (!$.isEmptyObject(specialPackages)) {
                // Get value selected of special package.
                let $container = $summary.find('.additionals-info .value').html('');
                $('.field-summary.additionals-info').removeClass('field-hidden');

                $.each(specialPackages, function (key, value) {
                  // Print each special package selected.
                  let item = $('<span>').addClass('item').text(value.pack_name + ' hasta por ' + value.pack_duration);
                  $container.append(item);
                  special_packages_selected += '|' + value.pack_id;
                });
              }
              $('#create-plan-form .field-packages-id').val(package_selected + special_packages_selected);
              clearInterval(interval);
            }
          }, 500);

          // Values hidden.
          let price_product = parseInt(Drupal.clcCreatePlan.orderPrice.product);
          let price_special_packs = parseInt(Drupal.clcCreatePlan.orderPrice.special);
          let price_packs = parseInt(Drupal.clcCreatePlan.orderPrice.pack);
          let value_order = price_product + price_special_packs + price_packs;
          $('#create-plan-form .field-order-value').val(value_order);
        }
      });

      // Validate fields in submit.
      $('#create-plan-form .form-submit').click(function (e) {
        if (Drupal.ValidateOnceTime(e.timeStamp)) {
          e.preventDefault();
          $('.msg-error').remove();
          let validateRequired = Drupal.validateFieldsRequired();
          let validateEmail = Drupal.validateFieldsEmail();
          let validateCellphone = Drupal.validateFieldsCellphone();
          let validate = validateEmail * validateRequired * validateCellphone;

          if (validate) {
            let elem_name = $(this).attr('name');
            $('#page-loader').fadeIn('slow');
            $('.field-btn-triggering').val(elem_name);

            Drupal.consumeWebServiceSuma(elem_name);
          }
        }
      });

    }
  };

  // This function validates if a value exists in the array.
  Drupal.InternetInArray = function (needle, haystack) {
    for (var i = 0; i < haystack.length; i++) {
      if (haystack[i].internet == needle) return true;
    }

    return false;
  }
  function barMin() {
    var barMin = $('<div>', {
      id: 'barMin',
      class: 'barMin'
    });
    var barInt = $('<div>', {
      id: 'barInt',
      class: 'barMin'
    });
    var barMinColor = $('<div>', {
      id: 'barMinColor',
      class: 'barMinColor'
    });
    var barIntColor = $('<div>', {
      id: 'barIntColor',
      class: 'barMinColor'
    });
    var toggle = $('<div>', {
      class: 'toggle-button'
    });
    var toggleInt = $('<div>', {
      class: 'toggle-button'
    });
    var bar = $("#barMin");
    var selMin = $("#edit-minuts--wrapper");
    var selMinToggle = selMin.find(".toggle-button");
    var selDat = $("#edit-internet--wrapper");
    var selDatToggle = selDat.find(".toggle-button");

    if (bar.length < 1) {
      $("#edit-minuts--wrapper").prepend(barMin);
      $("#edit-internet--wrapper").prepend(barInt);
      $("#barMin").append(barMinColor);
      $("#barInt").append(barIntColor);
      $(".form-item-minuts").prepend(toggle);
      $(".form-item-internet").prepend(toggleInt);
    }
    $(".form-radio:checked").closest(".form-item-minuts").find(".toggle-button").addClass("active");
    $(".form-radio:checked").closest(".form-item-internet").find(".toggle-button").addClass("active");
    $("#edit-internet--wrapper .form-radio:disabled").prev().addClass("disabled");

    var xpos = $("#edit-minuts--wrapper .toggle-button").position();
    $("#barMinColor").width(xpos.left);
    var xposDat = $("#edit-internet--wrapper .toggle-button").position();
    $("#barIntColor").width(xposDat.left);

    $("#edit-minuts--wrapper .toggle-button").click(function () {
      var xCpos = $(this).position();
      $("#barMinColor").width(xCpos.left)
      $("#edit-minuts--wrapper .toggle-button").removeClass('active');
      selMinToggle.find(".form-radio").click();
      $(this).addClass('active');
      $(this).next().click();
      $("#edit-internet--wrapper").find(".toggle-button").removeClass('active');
      setTimeout(function () {
        selDat.find(".form-radio:checked").prev().addClass('active');
        //var xposDat = selDat.find( ".toggle-button.active" ).offset().left - $("#barInt").offset().left;
        var xposDat = selDat.find(".toggle-button.active").position();
        $("#barIntColor").width(xposDat.left);
        $("#edit-internet--wrapper .toggle-button").removeClass("disabled")
        $("#edit-internet--wrapper  .form-radio:disabled").prev().addClass("disabled");
      }, 500);
    });
    $("#edit-internet--wrapper .toggle-button").click(function () {
      var xCposDat = $(this).position();
      $("#barIntColor").width(xCposDat.left);
      $("#edit-internet--wrapper").find(".toggle-button").removeClass('active');
      selDatToggle.find(".form-radio").click();
      $(this).addClass('active');
      $(this).next().click();
    });
    $(".goto-summary").attr("href", "javascript:void(0)");
    $(".goto-summary").click(
      () => {
        $("#edit-summary").addClass("active");
        if ($(".alpha-resume").length < 1) {
          $("#content").append('<div class="alpha-resume"></div>');
          $("#edit-summary").append('<div class="close-resume">X</div>');
        }
        $(".close-resume").click(
          () => {
            $(".alpha-resume").remove();
            $(".close-resume").remove();
            $("#edit-summary").removeClass("active");
          }
        );
      }
    );
  }
  Drupal.getInternetByProduct = function (minut_selected) {
    // Execute the ajax functionallity.
    $.ajax({
      url: "/ajax-get-products",
      type: "POST",
      data: {
        'minut_selected': minut_selected,
      },
      dataType: "json",
      success: function (data, textStatus, jqXHR) {
        // Wrapper input internet.
        let $fields_int = $('#edit-internet .form-item-internet');
        // To activate first internet available by minut.
        let active_internet = false;

        // Disable and enable internet fields.
        $.each($fields_int, function (key, value) {
          let $input = $(this).find('input');
          let $label = $(this).find('label');
          let validate = Drupal.InternetInArray($input.val(), data);

          // Disable radios not available.
          if (!validate) {
            $input.prop('disabled', true);
            $label.addClass(classLabel);
          }
          // Enable radios available.
          else {
            $input.prop('disabled', false);
            $label.removeClass(classLabel);

            // Active the first internet available.
            if (!active_internet) {
              $input.prop('checked', true);
              active_internet = true;

              // Set value of order.
              Drupal.clcCreatePlan.orderPrice.product = data[0].value;
              Drupal.SetOrderPrice();
            }
          }
        });
      }
    });
    barMin();
  }

  // This function validates if a value exists in the array.
  Drupal.getPriceProductAjax = function (minut_selected, internet_selected) {
    // This variable allows to execute the ajax only one time.
    let now = Date.now();
    if (Drupal.ValidateOnceTime(now)) {
      // Execute the ajax functionallity.
      $.ajax({
        url: "/ajax-get-product-price",
        type: "POST",
        data: {
          'minut_selected': minut_selected,
          'internet_selected': internet_selected,
        },
        dataType: "json",
        success: function (data, textStatus, jqXHR) {
          // Set value of order.
          Drupal.clcCreatePlan.orderPrice.product = data[0];
          Drupal.SetOrderPrice();
        }
      });
    }
  }

  // Get price of packages only Minuts and only Internet.
  Drupal.getPricePackage = function (pack_id) {
    // Execute the ajax functionallity.
    $.ajax({
      url: "/ajax-get-package-price",
      type: "POST",
      data: {
        'pack_id': pack_id,
      },
      dataType: "json",
      success: function (data, textStatus, jqXHR) {
        // Set value of order.
        Drupal.clcCreatePlan.orderPrice.pack = data;
        Drupal.SetOrderPrice();
      }
    });
  }

  // This function format order price.
  Drupal.FormatOrderPrice = function (value) {
    if (value > 0) {
      let options = { currency: 'COP', maximumFractionDigits: 0 };
      let numberFormat2 = new Intl.NumberFormat('en-US', options);

      return numberFormat2.format(value);
    }

    return false;
  }

  // This function set price in the box.
  Drupal.SetOrderPrice = function () {
    // This variable allows to execute the ajax only one time.
    let now = Date.now();
    if (Drupal.ValidateOnceTime(now)) {
      // Fields.
      let $orderValue = $('.order-value .value');
      let price_product = parseInt(Drupal.clcCreatePlan.orderPrice.product);
      let price_special_packs = parseInt(Drupal.clcCreatePlan.orderPrice.special);
      let price_packs = parseInt(Drupal.clcCreatePlan.orderPrice.pack);
      let value_order = price_product + price_special_packs + price_packs;

      value_order = Drupal.FormatOrderPrice(value_order);
      $orderValue.text(value_order);
    }
  }

  // This function validate to execute the function only one time.
  Drupal.ValidateOnceTime = function (timestamp) {
    let validate = false;

    if (Drupal.behaviors.timeStampEvent != timestamp) {
      validate = true;
      Drupal.behaviors.timeStampEvent = timestamp;
    }

    return validate;
  }

  // This function validate fields required.
  Drupal.validateFieldsRequired = function () {
    var $fields = $('#create-plan-form .field-required');
    var validate = true;

    $fields.each(function (key, value) {
      let $parent = $(this).parents('.form-item');
      let field_name = $(this).attr('data-name');
      if (!$(this).val()) {
        $parent.addClass('field-error');
        $parent.append('<span class="msg-error">El campo ' + field_name + ' es requerido.</span>');
        validate = false;
      }

      if ($(this).hasClass('field-check') && !$(this).prop('checked')) {
        $parent.addClass('field-error');
        $parent.append('<span class="msg-error">Debes aceptar ' + field_name + '.</span>');
        validate = false;
      }
    });

    return validate;
  }

  // This function validate field type email.
  Drupal.validateFieldsEmail = function () {
    var $fields = $('#create-plan-form .field-email');
    var validate = true;
    // Expresion regular para validar el correo
    var regex = /^[a-zA-Z0-9\._-]+@[a-zA-Z0-9-]{2,}([.][a-zA-Z]{2,4}){1,2}$/;

    $fields.each(function (key, value) {
      let $parent = $(this).parents('.form-item');
      let email = $(this).val();

      if (email && !regex.test(email.trim())) {
        $parent.addClass('field-error');
        $parent.append('<span class="msg-error">El formato ingresado no es válido.</span>');
        validate = false;
      }
    });

    return validate;
  }

  // This function validate field type phone.
  Drupal.validateFieldsCellphone = function () {
    var $fields = $('#create-plan-form .field-cellphone');
    var validate = true;

    $fields.each(function (key, value) {
      let $parent = $(this).parents('.form-item');
      let this_val = $(this).val();
      // Expresion regular para validar el inicio por numero 3.
      let regex = /^3[0-9]{9}$/;

      if (this_val && !regex.test(this_val.trim())) {
        $parent.addClass('field-error');
        $parent.append('<span class="msg-error">El número de celular ingresado no es válido.</span>');
        validate = false;
      }
    });

    return validate;
  }

  Drupal.consumeWebServiceSuma = function (name_btn) {
    let $field_cellphone = $('#create-plan-form input.field-cellphone');
    let packages_id = $('#create-plan-form input.field-packages-id').val();
    let order_value = $('#create-plan-form input.field-order-value').val();

    // Execute the ajax functionallity.
    $.ajax({
      url: "/ajax-suma-ws",
      type: "POST",
      data: {
        'name_btn': name_btn,
        'cellphone': $field_cellphone.val(),
        'packages_id': packages_id,
        'order_value': order_value
      },
      dataType: "json",
      success: function (data, textStatus, jqXHR) {
        if (data.error) {
          let $parent = $field_cellphone.parents('.form-item');
          $parent.addClass('field-error');
          $parent.append('<span class="msg-error">' + data.msg_error + '</span>');
          $('#page-loader').fadeOut('slow');
        }
        else {
          // Go to PSE. Buy button.
          if (data.url_redirect && data.body) {
            let url = data.url_redirect + '?';
            let body = JSON.parse(data.body);

            // Add parameters get in URL.
            $.each(body, function (key, token) {
              url += key + '=' + token;
            });

            $('#create-plan-form .field-order-redirect-url').val(url);
          }

          $('#create-plan-form').submit();
        }
      }
    });
  }
})(jQuery);

(function ($, Drupal) {
  //This function add toggle buttons and progress bar in "Arma tu plan"
  function barMin() {
    var barMin = $('<div>', {
      id: 'barMin',
      class: 'barMin'
    });
    var barInt = $('<div>', {
      id: 'barInt',
      class: 'barMin'
    });
    var barMinColor = $('<div>', {
      id: 'barMinColor',
      class: 'barMinColor'
    });
    var barIntColor = $('<div>', {
      id: 'barIntColor',
      class: 'barMinColor'
    });
    var toggle = $('<div>', {
      class: 'toggle-button'
    });
    var toggleInt = $('<div>', {
      class: 'toggle-button'
    });
    var bar = $("#barMin");
    var selMin = $("#edit-minuts--wrapper");
    var selMinToggle = selMin.find(".toggle-button");
    var selDat = $("#edit-internet--wrapper");
    var selDatToggle = selDat.find(".toggle-button");

    if (bar.length < 1) {
      $("#edit-minuts--wrapper").prepend(barMin);
      $("#edit-internet--wrapper").prepend(barInt);
      $("#barMin").append(barMinColor);
      $("#barInt").append(barIntColor);
      $(".form-item-minuts").prepend(toggle);
      $(".form-item-internet").prepend(toggleInt);
    }
    $(".form-radio:checked").closest(".form-item-minuts").find(".toggle-button").addClass("active");
    $(".form-radio:checked").closest(".form-item-internet").find(".toggle-button").addClass("active");
    $("#edit-internet--wrapper .form-radio:disabled").prev().addClass("disabled");

    var xpos = $("#edit-minuts--wrapper .toggle-button").position();
    $("#barMinColor").width(xpos.left);
    var xposDat = $("#edit-internet--wrapper .toggle-button").position();
    $("#barIntColor").width(xposDat.left);

    $("#edit-minuts--wrapper .toggle-button").click(function () {
      var xCpos = $(this).position();
      $("#barMinColor").width(xCpos.left)
      $("#edit-minuts--wrapper .toggle-button").removeClass('active');
      selMinToggle.find(".form-radio").click();
      $(this).addClass('active');
      $(this).next().click();
      $("#edit-internet--wrapper").find(".toggle-button").removeClass('active');
      setTimeout(function () {
        selDat.find(".form-radio:checked").prev().addClass('active');
        var xposDat = selDat.find(".toggle-button.active").position();
        $("#barIntColor").width(xposDat.left);
        $("#edit-internet--wrapper .toggle-button").removeClass("disabled")
        $("#edit-internet--wrapper  .form-radio:disabled").prev().addClass("disabled");
      }, 500);
    });
    $("#edit-internet--wrapper .toggle-button").click(function () {
      var xCposDat = $(this).position();
      $("#barIntColor").width(xCposDat.left);
      $("#edit-internet--wrapper").find(".toggle-button").removeClass('active');
      selDatToggle.find(".form-radio").click();
      $(this).addClass('active');
      $(this).next().click();
    });
    $(".goto-summary").attr("href", "javascript:void(0)");
    $(".goto-summary").click(
      () => {
        $("#edit-summary").addClass("active");
        if ($(".alpha-resume").length < 1) {
          $("#content").append('<div class="alpha-resume"></div>');
          $("#edit-summary").append('<div class="close-resume">X</div>');
        }
        $(".close-resume").click(
          () => {
            $(".alpha-resume").remove();
            $(".close-resume").remove();
            $("#edit-summary").removeClass("active");
          }
        );
      }
    );
  }
  function toggleButtons() {
    var main = $("#edit-packages-minuts--wrapper");
    var mainDos = $("#edit-packages-internet--wrapper");
    var content = main.find("#edit-packages-minuts");
    var contentDos = mainDos.find("#edit-packages-internet");
    var toggleMin = $('<div>', {
      class: 'toggle-Min'
    });
    var toggleInt = $('<div>', {
      class: 'toggle-Min'
    });
    var toggleMinD = $(".toggle-Min");
    if (toggleMinD.length < 1) {
      content.find(".form-item").prepend(toggleMin);
      contentDos.find(".form-item").prepend(toggleInt);
    }
    $(".toggle-Min").click(
      function () {
        $(".toggle-Min").removeClass("active");
        $(this).addClass("active");
        $(this).next().click();
      }
    );
    $("#edit-packages-internet--wrapper").find(".fieldset-legend").click(
      function () {
        $(this).closest("#edit-packages-internet--wrapper").find(".form-item:nth-child(1)").find(".toggle-Min").click();
        $("#edit-packages-minuts--wrapper").find(".toggle-Min").removeClass("active");
        $("#edit-internet--wrapper").find(".fieldset-legend").removeClass("active");
        $("#edit-minuts--wrapper").find(".fieldset-legend").removeClass("active");
        setTimeout(
          function () { $("#edit-packages-internet-arm-solo-datos-1").click(); }, 300
        );
      }
    );
    $("#edit-packages-minuts--wrapper").find(".fieldset-legend").click(
      function () {
        $(this).closest("#edit-packages-minuts--wrapper").find(".form-item:nth-child(1)").find(".toggle-Min").click();
        $("#edit-packages-internet-arm-solo-datos-1").click();
        $("#edit-packages-internet--wrapper").find(".toggle-Min").removeClass("active");
        $("#edit-internet--wrapper").find(".fieldset-legend").removeClass("active");
        $("#edit-minuts--wrapper").find(".fieldset-legend").removeClass("active");
        let dataTime = document.getElementsByClassName("duration");
        debugger
        for (var i=0; i < dataTime.length; i++) {
         
           let data =  dataTime[i].querySelector("strong");
           if(data.innerText === "15"){
            data.innerText = "30";
           }
          
        }
        // if(){

        // }
        setTimeout(
          function () { $("#edit-packages-minuts-arm-solo-voz-1").click(); }, 300
        );
      }
    );
  }
  window.onload = function () {

    toggleButtons()
    barMin();
    var navVer = navigator.userAgent.match(/Chrom(?:e|ium)\/([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)/);

    if (navigator.userAgent.indexOf("Chrome") > -1) {

      if (navVer[1] < 88) {
        document.getElementById("edit-minuts--wrapper--description").classList.add("chrome");
        document.getElementById("edit-internet--wrapper--description").classList.add("chrome");
        document.getElementById("edit-minuts--wrapper").classList.add("chrome");
        document.getElementById("edit-internet--wrapper").classList.add("chrome");
      }
    }
    function timerBar() {
      setTimeout(
        function () {
          var xpos = $("#edit-minuts--wrapper").find(".toggle-button.active").position();
          $("#barMinColor").width(xpos.left);
          var xposDat = $("#edit-internet--wrapper").find(".toggle-button.active").position();
          $("#barIntColor").width(xposDat.left);
        }, 500
      );
    }
    $("#edit-internet--wrapper").find(".fieldset-legend").addClass("active");
    $("#edit-minuts--wrapper").find(".fieldset-legend").addClass("active");
    $("#edit-internet--wrapper").find(".fieldset-legend").click(
      function () {
        $("#edit-internet--wrapper").find(".fieldset-legend").addClass("active");
        $("#edit-minuts--wrapper").find(".fieldset-legend").addClass("active");
        $('.toggle-Min').removeClass('active');
        $('#edit-internet--wrapper').find('.toggle-button').removeClass('active');
        timerBar();
      }
    );
    $("#edit-minuts--wrapper").find(".fieldset-legend").click(
      function () {
        $("#edit-internet--wrapper").find(".fieldset-legend").addClass("active");
        $("#edit-minuts--wrapper").find(".fieldset-legend").addClass("active");
        $('.toggle-Min').removeClass('active');
        timerBar();
      }
    );

  }

  //-End-This function add toggle buttons and progress bar in "Arma tu plan"
}(jQuery, Drupal));
