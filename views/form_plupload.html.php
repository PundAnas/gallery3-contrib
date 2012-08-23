<?php defined("SYSPATH") or die("No direct script access.") ?>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/plupload.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/plupload.flash.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/plupload.html5.js") ?>"></script>
<script type="text/javascript" src="<?= url::file("modules/plupload/lib/jquery.ui.plupload/jquery.ui.plupload.js") ?>"></script>
<script type="text/javascript">
// Convert divs to queue widgets when the DOM is ready
$("#g-add-photos-canvas").ready($(function() {
  debugger;
  
  //$("#g-plupload").plupload({
  var uploader = new plupload.Uploader({
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

    // Resize images on clientside if we can
    resize : {width : 320, height : 240, quality : 90},
    
    // Rename files by clicking on their titles
    rename: true,
    
    // Sort files
    sortable: true,

    // Specify what files to browse for
    filters : [
      {title : "Image files", extensions : "jpg,gif,png"},
    ],

    // Flash settings
    flash_swf_url : '../../js/plupload.flash.swf',
    onClearQueue: function(event) {
      $("#g-upload-cancel-all")
        .addClass("ui-state-disabled")
        .attr("disabled", "disabled");
      $("#g-upload-done")
        .removeClass("ui-state-disabled")
        .attr("disabled", null);
      return true;
    },
    onComplete: function(event, queueID, fileObj, response, data) {
      var re = /^error: (.*)$/i;
      var msg = re.exec(response);
      $("#g-add-photos-status ul").append(
        "<li id=\"q" + queueID + "\" class=\"g-success\"><span></span> - " +
        <?= t("Completed")->for_js() ?> + "</li>");
      $("#g-add-photos-status li#q" + queueID + " span").text(fileObj.name);
      setTimeout(function() { $("#q" + queueID).slideUp("slow").remove() }, 5000);
      success_count++;
      update_status();
      return true;
    },
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
      $("#g-uploadify").uploadifyCancel(queueID);
      error_count++;
      update_status();
    },
    onSelect: function(event) {
    }
  }); 
  uploader.bind('Init', function(up, params) {
    $('#filelist').html("<div><b>Debug :</b>Current runtime: " + params.runtime + "</div>");
  });  

  uploader.init();

  uploader.bind('FilesAdded', function(up, files) {
    $.each(files, function(i, file) {
      $('#filelist').append(
        '<div id="' + file.id + '">' +
        file.name + ' (' + plupload.formatSize(file.size) + ') <b></b>' +
      '</div>');    
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
    $('#' + file.id + " b").html(file.percent + "%");
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
    $('#' + file.id + " b").html("100%");
    $("#g-upload-cancel-all")
      .addClass("ui-state-disabled")
      .attr("disabled", "disabled");
    $("#g-upload-done")
      .removeClass("ui-state-disabled")
      .attr("disabled", null);
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
</div>