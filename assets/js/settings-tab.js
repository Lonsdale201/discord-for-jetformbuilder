(function (wp) {
  if (!wp || !wp.hooks || !wp.i18n) {
    return;
  }

  const { addFilter } = wp.hooks;
  const { __ } = wp.i18n;

  const DiscordSettingsTab = {
    name: "discord-settings-tab",
    props: {
      incoming: {
        type: Object,
        default() {
          return {};
        },
      },
    },
    data() {
      return {
        current: {
          webhook: "",
          failure_webhook: "",
          notify_failures_only: false,
        },
        labels: {
          webhook: __("Discord webhook URL", "discord-for-jetformbuilder"),
          description: __(
            "Paste the Discord webhook URL that JetFormBuilder should use for notifications.",
            "discord-for-jetformbuilder"
          ),
          failureWebhook: __(
            "Submission notification webhook URL",
            "discord-for-jetformbuilder"
          ),
          failureDescription: __(
            "Optional webhook that receives submission summaries (or only failures if enabled).",
            "discord-for-jetformbuilder"
          ),
          onlyFailed: __(
            "Only failed submissions",
            "discord-for-jetformbuilder"
          ),
          onlyFailedDescription: __(
            "Send notifications only when at least one action fails.",
            "discord-for-jetformbuilder"
          ),
        },
      };
    },
    created() {
      this.current = Object.assign({}, this.current, this.incoming || {});
      this.current.notify_failures_only = !!this.current.notify_failures_only;
    },
    methods: {
      getRequestOnSave() {
        return {
          data: this.current,
        };
      },
    },
    render(h) {
      return h("div", [
        h("cx-vui-input", {
          attrs: {
            label: this.labels.webhook,
            description: this.labels.description,
            size: "fullwidth",
            "wrapper-css": ["equalwidth"],
          },
          model: {
            value: this.current.webhook,
            callback: (value) => {
              this.$set(this.current, "webhook", value);
            },
            expression: "current.webhook",
          },
        }),
        h("cx-vui-input", {
          attrs: {
            label: this.labels.failureWebhook,
            description: this.labels.failureDescription,
            size: "fullwidth",
            "wrapper-css": ["equalwidth"],
          },
          model: {
            value: this.current.failure_webhook,
            callback: (value) => {
              this.$set(this.current, "failure_webhook", value);
            },
            expression: "current.failure_webhook",
          },
        }),
        h("cx-vui-switcher", {
          attrs: {
            label: this.labels.onlyFailed,
            description: this.labels.onlyFailedDescription,
            "wrapper-css": ["equalwidth"],
          },
          model: {
            value: !!this.current.notify_failures_only,
            callback: (value) => {
              this.$set(this.current, "notify_failures_only", !!value);
            },
            expression: "current.notify_failures_only",
          },
        }),
      ]);
    },
  };

  const tabDefinition = {
    title: __("Discord", "discord-for-jetformbuilder"),
    component: DiscordSettingsTab,
  };

  addFilter(
    "jet.fb.register.settings-page.tabs",
    "discord-for-jetformbuilder/settings-tab",
    (tabs) => {
      tabs.push(tabDefinition);

      return tabs;
    }
  );
})(window.wp);
