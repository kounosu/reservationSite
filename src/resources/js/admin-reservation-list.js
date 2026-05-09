const dialogs = document.querySelectorAll('.admin-reservation-dialog');

if (dialogs.length > 0) {
    /**
     * 予約詳細ダイアログの開閉ボタンを処理する。
     *
     * @param {MouseEvent} event
     */
    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-dialog-open]');

        if (openButton) {
            const dialog = document.getElementById(openButton.dataset.dialogOpen);

            if (dialog instanceof HTMLDialogElement) {
                dialog.showModal();
            }

            return;
        }

        const closeButton = event.target.closest('[data-dialog-close]');

        if (closeButton) {
            const dialog = closeButton.closest('dialog');

            if (dialog instanceof HTMLDialogElement) {
                dialog.close();
            }
        }
    });

    dialogs.forEach((dialog) => {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        /**
         * ダイアログ外側のクリックで予約詳細ダイアログを閉じる。
         *
         * @param {MouseEvent} event
         */
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            const isInsideDialog =
                rect.top <= event.clientY &&
                event.clientY <= rect.top + rect.height &&
                rect.left <= event.clientX &&
                event.clientX <= rect.left + rect.width;

            if (!isInsideDialog) {
                dialog.close();
            }
        });
    });
}
