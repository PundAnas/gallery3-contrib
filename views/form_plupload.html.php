<?php defined("SYSPATH") or die("No direct script access.") ?>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/plupload.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/plupload.flash.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/plupload.html5.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/jquery.ui.plupload/jquery.ui.plupload_clear.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/jquery.ui.plupload/jquery.debug.js") ?>"></script>
<script type="text/javascript">
// Convert divs to queue widgets when the DOM is ready
$("#g-add-photos-canvas").ready($(function() {
  var success_count = 0, error_count = 0, updating = 0;
  var uploader;

  var update_status = function() {
    if (updating) {
      // poor man's mutex
      setTimeout(function() { update_status(); }, 500);
    }
    updating = 1;
    $.get("<?= url::site("uploader/status/_S/_E") ?>"
          .replace("_S", success_count).replace("_E", error_count),
        function(data) {
          $("#g-add-photos-status-message").html(data);
          updating = 0;
        });
  };

  function remove_file_queue(file, spanclass) {
    $('#g-plupload' + file.id).slideUp("slow").remove();     
    if (spanclass == "g-success") {
      spantext = <?= t("Completed")->for_js() ?>; 
    } else {
      spantext = <?= t("Cancelled")->for_js() ?>; 
    }
    $("#g-add-photos-status ul").append(
        "<li id=\"q" + file.id + "\" class=\"" + spanclass + "\"><span></span> - " + spantext + "</li>");
    $("#g-add-photos-status li#q" + file.id + " span").text(file.name);
    setTimeout(function() { $("#q" + file.id).slideUp("slow").remove() }, 5000);         
  }

  function cancel_all_upload() {
    var files = uploader.files;
    while (files.length > 0) {
      _cancel_upload(files[0]);
    }
  }  

  function cancel_upload(id) {
    _cancel_upload(uploader.getFile(id));
  }

  function _cancel_upload(file) {       
    var status_before = file.status;
    remove_file_queue(file, "g-error");
    uploader.removeFile(file);                
    if(uploader.state == plupload.STARTED && status_before == plupload.UPLOADING) {
      uploader.stop();
      uploader.start();
    }
  }
 
  // If using g-plupload $("#g-plupload").plupload('getUploader') return nothing (uploader doesn't exist yet)
  //$("#g-plupload").plupload({
  uploader = new plupload.Uploader({
    // General settings
    runtimes : 'html5,flash',
    url : "<?= url::site("uploader/add_photo/{$album->id}") ?>",
    max_file_size : <?= $size_limit_bytes ?>,
    max_file_count: <?= $simultaneous_upload_limit ?>, // user can add no more then 20 files at a time
    chunk_size : '1mb',
    unique_names : true,
    multiple_queues : true,
    browse_button : 'g-add-photos-button',
    container : "g-add-photos-canvas",
    multipart : true,
    multipart_params : <?= json_encode($script_data) ?>,

    // Resize images on clientside if we can
    resize : {width : 320, height : 240, quality : 90},
    
    // Rename files by clicking on their titles
    rename: true,
    
    // Sort files
    sortable: true,

    // Specify what files to browse for
    filters : [
      {title : "Image files", extensions : "jpg,JPG,jpeg,JPEG,gif,GIF,png,PNG"},
    ],

    // Flash settings
    flash_swf_url : '../../js/plupload.flash.swf',
    onError: function(event, queueID, fileObj, errorObj) {
      if (errorObj.type == "HTTP") {
        if (errorObj.info == "500") {
          error_msg = <?= t("Unable to process this photo")->for_js() ?>;
        } else if (errorObj.info == "404") {
          error_msg = <?= t("The upload script was not found")->for_js() ?>;
        } else if (errorObj.info == "400") {
          error_msg = <?= t("This photo is too large (max is %size bytes)",
                            array("size" => $size_limit))->for_js() ?>;
        } else {
          msg += (<?= t("Server error: __INFO__ (__TYPE__)")->for_js() ?>
            .replace("__INFO__", errorObj.info)
            .replace("__TYPE__", errorObj.type));
        }
      } else if (errorObj.type == "File Size") {
        error_msg = <?= t("This photo is too large (max is %size bytes)",
                          array("size" => $size_limit))->for_js() ?>;
      } else {
        error_msg = <?= t("Server error: __INFO__ (__TYPE__)")->for_js() ?>
                    .replace("__INFO__", errorObj.info)
                    .replace("__TYPE__", errorObj.type);
      }
      msg = " - <a target=\"_blank\" href=\"http://codex.gallery2.org/Gallery3:Troubleshooting:Uploading\">" +
        error_msg + "</a>";

      $("#g-add-photos-status ul").append(
        "<li id=\"q" + queueID + "\" class=\"g-error\"><span></span>" + msg + "</li>");
      $("#g-add-photos-status li#q" + queueID + " span").text(fileObj.name);
      $("#g-plupload").uploadifyCancel(queueID);
      error_count++;
      update_status();
    } 
      
  }); 
  // http://www.plupload.com/example_jquery_ui.php
  //var uploader = $("#g-plupload").plupload('getUploader');

  uploader.bind('Init', function(up, params) {
    $('#filelist').html("<div><b>Debug :</b>Current runtime: " + params.runtime + "</div>");
  }); 

  uploader.init();   

  uploader.bind('FilesAdded', function(up, files) {
    $('#g-add-photos-canvas').append(
        '<div id="g-pluploadQueue" class="uploadifyQueue">');
    $.each(files, function(i, file) {
      $('#g-pluploadQueue').append(
        '<div id="g-plupload' + file.id + '" class="uploadifyQueueItem">' + 
        '<div class="cancel">' + 
          '<a id="g-plupload' + file.id + 'Cancel" href="#" ><img src="/lib/uploadify/cancel.png" border="0"></a>' +
        '</div>' + 
        '<span class="fileName">"' + file.name + '" (' + plupload.formatSize(file.size) + ')</span>' +
        '<span id="g-plupload' + file.id + 'Percentage" class="percentage"></span>' +
        '<div class="uploadifyProgress">' +
          '<div id="g-plupload' + file.id + 'ProgressBar" class="uploadifyProgressBar"><!--Progress Bar--></div>' + 
        '</div>' + 
      '</div>');
      $('#g-plupload' + file.id + 'Cancel').bind('click', function(event) {
        var reg = /g-plupload(.*)Cancel/g;
        cancel_upload(reg.exec(this.id)[1]);
      });       
    });
    uploader.start();
    if ($("#g-upload-cancel-all").hasClass("ui-state-disabled")) {
      $("#g-upload-cancel-all")
        .removeClass("ui-state-disabled")
        .attr("disabled", null);
      $("#g-upload-done")
        .addClass("ui-state-disabled")
        .attr("disabled", "disabled");
    }      
    e.preventDefault();
    up.refresh(); // Reposition Flash/Silverlight
  });

  uploader.bind('UploadProgress', function(up, file) {
    $('#g-plupload' + file.id + 'ProgressBar').css('width', file.percent + '%');
    if (file.percent != 100) {
      $('#g-plupload' + file.id + 'Percentage').text(' - ' +file.percent + '%');
    } else {
      $('#g-plupload' + file.id + 'Percentage').text(' - Completed');
    }
  });

  uploader.bind('Error', function(up, err) {
    $('#filelist').append("<div>Error: " + err.code +
      ", Message: " + err.message +
      (err.file ? ", File: " + err.file.name : "") +
      "</div>"
    );

    up.refresh(); // Reposition Flash/Silverlight
  });

  uploader.bind('FileUploaded', function(up, file) {    
   /* $('#g-plupload' + file.id).slideUp("slow").remove();     
    $("#g-add-photos-status ul").append(
        "<li id=\"q" + file.id + "\" class=\"g-success\"><span></span> - " +
        <?= t("Completed")->for_js() ?> + "</li>");
    $("#g-add-photos-status li#q" + file.id + " span").text(file.name);
    setTimeout(function() { $("#q" + file.id).slideUp("slow").remove() }, 5000);     */
    remove_file_queue(file, "g-success");
    success_count++;
    update_status();    
  });

  uploader.bind('UploadComplete', function(up, files) {
    $("#g-upload-cancel-all")
      .addClass("ui-state-disabled")
      .attr("disabled", "disabled");
    $("#g-upload-done")
      .removeClass("ui-state-disabled")
      .attr("disabled", null);
  });

  $('#g-upload-cancel-all').bind('click', function(event){
    cancel_all_upload();
    uploader.trigger('UploadComplete');
    return false;
  });
  
})
);
</script>

  <div>
    <ul class="g-breadcrumbs">
      <? foreach ($album->parents() as $i => $parent): ?>
      <li<? if ($i == 0) print " class=\"g-first\"" ?>> <?= html::clean($parent->title) ?> </li>
      <? endforeach ?>
      <li class="g-active"> <?= html::purify($album->title) ?> </li>
    </ul>
  </div>

  <div id="g-add-photos-canvas">
    <button id="g-add-photos-button" class="g-button ui-state-default ui-corner-all" href="#"><?= t("Select photos (%size max per file)...", array("size" => $size_limit)) ?></button>
    <div id="filelist">No runtime found.</div>    
    <span id="g-plupload"></span>
  </div>
  <div id="g-add-photos-status">
    <ul id="g-action-status" class="g-message-block">
    </ul>
  </div>
 <!-- Proxy the done request back to our form, since its been ajaxified -->
  <button id="g-upload-done" class="ui-state-default ui-corner-all" onclick="$('#g-add-photos-form').submit();return false;">
  <?= t("Done") ?>
  </button>
  <button id="g-upload-cancel-all" class="ui-state-default ui-corner-all ui-state-disabled" disabled="disabled">
  <?= t("Cancel uploads") ?>
  </button>
  <span id="g-add-photos-status-message" />
</div>
