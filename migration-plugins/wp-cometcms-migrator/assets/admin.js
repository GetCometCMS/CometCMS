(function ($) {
  const log = $("#cometcms-log");
  const progress = $(".cometcms-progress");
  const progressBar = $(".cometcms-progress-bar span");
  const progressText = $(".cometcms-progress-text");

  function append(message) {
    const time = new Date().toLocaleTimeString();
    log.text(log.text() + "[" + time + "] " + message + "\n");
    log.scrollTop(log[0].scrollHeight);
  }

  function request(action, data) {
    return $.post(CometCMSMigrator.ajaxUrl, {
      action: action,
      nonce: CometCMSMigrator.nonce,
      ...data,
    });
  }

  function setProgress(done, total) {
    const percent = total > 0 ? Math.round((done / total) * 100) : 0;
    progress.removeAttr("hidden");
    progressBar.css("width", percent + "%");
    progressText.text(done + " / " + total + " migrated");
  }

  $("#cometcms-test").on("click", function () {
    append("Testing connection...");
    request("cometcms_test_connection", {})
      .done(function (response) {
        if (!response.success) {
          append("Connection failed: " + response.data.message);
          return;
        }
        append("Connected to " + (response.data.data.name || "CometCMS") + " " + (response.data.data.version || ""));
      })
      .fail(function (xhr) {
        append("Connection failed: " + (xhr.responseJSON?.data?.message || xhr.statusText));
      });
  });

  $("#cometcms-preview").on("click", function () {
    append("Loading WordPress counts...");
    request("cometcms_preview_counts", {}).done(function (response) {
      if (!response.success) {
        append("Preview failed: " + response.data.message);
        return;
      }
      const labels = response.data.labels || {};
      Object.entries(response.data.counts).forEach(function ([type, count]) {
        append((labels[type] || type) + ": " + count + " entries");
      });
    });
  });

  $("#cometcms-run").on("click", async function () {
    const runButton = $(this);
    runButton.prop("disabled", true);
    log.text("");
    append(CometCMSMigrator.strings.running);

    try {
      const preview = await request("cometcms_preview_counts", {});
      if (!preview.success) {
        append("Preview failed: " + preview.data.message);
        return;
      }

      const counts = preview.data.counts;
      const labels = preview.data.labels || {};
      const targets = Object.entries(counts)
        .filter(function ([, count]) {
          return Number(count) > 0;
        })
        .map(function ([type]) {
          return labels[type] || type;
        });
      append("Migrating " + (targets.length ? targets.join(", ") : "no entries"));

      const total = Object.values(counts).reduce(function (sum, count) {
        return sum + Number(count);
      }, 0);
      let migrated = 0;
      setProgress(0, total);

      for (const [postType, count] of Object.entries(counts)) {
        let offset = 0;
        const label = labels[postType] || postType;
        append("Starting " + label + " (" + count + ")");

        while (offset < count) {
          const response = await request("cometcms_migrate_batch", {
            post_type: postType,
            offset: offset,
            limit: CometCMSMigrator.batchSize,
          });

          if (!response.success) {
            append(label + " failed: " + response.data.message);
            break;
          }

          const batch = response.data;
          migrated += batch.processed;
          offset = batch.next_offset;
          setProgress(migrated, total);
          append(
            label +
              ": processed " +
              batch.processed +
              ", created " +
              batch.created +
              ", updated " +
              batch.updated +
              ", failed " +
              batch.failed
          );

          (batch.messages || []).forEach(append);

          if (batch.done) {
            break;
          }
        }
      }

      append(CometCMSMigrator.strings.done);
    } catch (error) {
      const data = error.responseJSON?.data;
      let message = data?.message || error.responseText || error.statusText || error;
      if (typeof message === "string" && message.length > 500) {
        message = message.substring(0, 500) + "...";
      }
      append(CometCMSMigrator.strings.failed + " " + message);
    } finally {
      runButton.prop("disabled", false);
    }
  });
})(jQuery);
