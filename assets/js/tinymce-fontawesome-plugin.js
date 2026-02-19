(function () {
  function initTinyMCEPlugin() {
    if (
      typeof tinymce === "undefined" ||
      typeof tinymce.PluginManager === "undefined"
    ) {
      setTimeout(initTinyMCEPlugin, 100);
      return;
    }

    tinymce.PluginManager.add("fontawesome_icons", function (editor, url) {
      var isLoading = false;
      var currentPage = 1;
      var hasMore = false;
      var searchTimeout = null;

      // Add button to toolbar
      editor.addButton("fontawesome_icons", {
        title: "Insert FontAwesome Icon",
        icon: "font-awesome fa-solid fa-icons",
        onclick: function () {
          openIconPicker();
        },
      });

      function openIconPicker() {
        currentPage = 1;
        hasMore = false;

        editor.windowManager.open({
          title: "Insert FontAwesome Icon",
          width: 600,
          height: 400,
          body: [
            {
              type: "textbox",
              name: "search",
              label: "Search icons",
              placeholder: "Search icons...",
              onkeyup: function (e) {
                var self = this;
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                  currentPage = 1;
                  doSearch(self.value(), false);
                }, 300);
              },
            },
            {
              type: "container",
              name: "iconGrid",
              html: '<div id="tinymce-fa-icon-grid" class="tinymce-fa-icon-grid"><div class="tinymce-fa-icon-loading">Loading icons...</div></div>',
            },
          ],
          onsubmit: function (e) {
            // Prevent default submit - icons are inserted via click
            e.preventDefault();
          },
          buttons: [
            {
              text: "Cancel",
              onclick: "close",
            },
          ],
        });

        // Initial load
        setTimeout(function () {
          doSearch("", false);
          setupScrollLoading();
        }, 100);
      }

      function setupScrollLoading() {
        var $grid = document.getElementById("tinymce-fa-icon-grid");
        if ($grid) {
          $grid.addEventListener("scroll", function () {
            if (isLoading || !hasMore) return;

            var scrollTop = $grid.scrollTop;
            var scrollHeight = $grid.scrollHeight;
            var height = $grid.clientHeight;

            if (scrollTop + height >= scrollHeight - 50) {
              currentPage++;
              var searchInput = document.querySelector(
                '.mce-textbox[aria-label="Search icons"]'
              );
              var searchTerm = searchInput ? searchInput.value : "";
              doSearch(searchTerm, true);
            }
          });
        }
      }

      function doSearch(searchTerm, append) {
        isLoading = true;
        var $grid = document.getElementById("tinymce-fa-icon-grid");

        if (!$grid) return;

        if (!append) {
          $grid.innerHTML =
            '<div class="tinymce-fa-icon-loading">Loading icons...</div>';
        } else {
          var loadingEl = $grid.querySelector(".tinymce-fa-icon-loading");
          if (loadingEl) loadingEl.remove();

          var loadingMore = document.createElement("div");
          loadingMore.className = "tinymce-fa-icon-loading";
          loadingMore.textContent = "Loading more...";
          $grid.appendChild(loadingMore);
        }

        var xhr = new XMLHttpRequest();
        xhr.open("POST", ajaxurl, true);
        xhr.setRequestHeader(
          "Content-Type",
          "application/x-www-form-urlencoded"
        );

        xhr.onreadystatechange = function () {
          if (xhr.readyState === 4) {
            isLoading = false;

            // Remove loading indicator
            var loadingEl = $grid.querySelector(".tinymce-fa-icon-loading");
            if (loadingEl) loadingEl.remove();

            if (xhr.status === 200) {
              try {
                var response = JSON.parse(xhr.responseText);

                if (!append) {
                  $grid.innerHTML = "";
                }

                if (response.results && response.results.length) {
                  response.results.forEach(function (item) {
                    var option = document.createElement("div");
                    option.className = "tinymce-fa-icon-option";
                    option.setAttribute("data-value", item.id);
                    option.setAttribute("data-label", item.label);
                    option.setAttribute("title", item.label);
                    option.innerHTML =
                      '<i class="' + escapeAttr(item.id) + '"></i>';

                    option.addEventListener("click", function () {
                      var iconClass = this.getAttribute("data-value");
                      insertIcon(iconClass);
                    });

                    $grid.appendChild(option);
                  });

                  hasMore = response.more;
                } else if (!append) {
                  $grid.innerHTML =
                    '<div class="tinymce-fa-icon-no-results">No icons found</div>';
                }
              } catch (e) {
                console.error("Error parsing response:", e);
              }
            }
          }
        };

        xhr.send(
          "action=acf/fields/fontawesome_icon/query&s=" +
            encodeURIComponent(searchTerm) +
            "&paged=" +
            currentPage
        );
      }

      function insertIcon(iconClass) {
        editor.insertContent(
          '<i class="' + escapeAttr(iconClass) + '">&#8203;</i>&nbsp;'
        );
        editor.windowManager.close();
      }

      function escapeAttr(str) {
        return String(str)
          .replace(/&/g, "&amp;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#39;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;");
      }
    });
  }

  initTinyMCEPlugin();
})();