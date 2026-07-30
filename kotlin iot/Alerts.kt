package com.example.celulariot.ui

import android.app.Activity
import cn.pedant.SweetAlert.SweetAlertDialog

object Alerts {

    fun success(activity: Activity, title: String, text: String? = null) =
        showManual(activity, SweetAlertDialog.SUCCESS_TYPE, title, text)

    fun error(activity: Activity, title: String, text: String? = null) =
        showManual(activity, SweetAlertDialog.ERROR_TYPE, title, text)

    fun info(activity: Activity, title: String, text: String? = null) =
        showManual(activity, SweetAlertDialog.NORMAL_TYPE, title, text)

    fun warn(activity: Activity, title: String, text: String? = null) =
        showManual(activity, SweetAlertDialog.WARNING_TYPE, title, text)

    fun loading(
        activity: Activity,
        title: String = "Cargando...",
        cancelable: Boolean = false
    ): SweetAlertDialog {
        val dlg = SweetAlertDialog(activity, SweetAlertDialog.PROGRESS_TYPE)
        dlg.titleText = title
        dlg.setCancelable(cancelable)
        dlg.show()
        return dlg
    }

    fun confirm(
        activity: Activity,
        title: String,
        text: String,
        confirmText: String = "Sí",
        cancelText: String = "No",
        onConfirm: () -> Unit
    ) {
        val dlg = SweetAlertDialog(activity, SweetAlertDialog.WARNING_TYPE)
            .setTitleText(title)
            .setContentText(text)
            .setConfirmText(confirmText)
            .setCancelText(cancelText)

        dlg.setCancelable(false) // bloquea back y toque fuera
        dlg.setConfirmClickListener { d: SweetAlertDialog ->
            d.dismissWithAnimation()
            onConfirm()
        }
        dlg.setCancelClickListener { d: SweetAlertDialog ->
            d.dismissWithAnimation()
        }
        dlg.show()
    }

    private fun showManual(
        activity: Activity,
        type: Int,
        title: String,
        text: String?
    ) {
        val dlg = SweetAlertDialog(activity, type)
            .setTitleText(title)
            .setConfirmText("Aceptar")

        if (!text.isNullOrBlank()) dlg.contentText = text
        dlg.setCancelable(false) // bloquea back y toque fuera

        dlg.setConfirmClickListener { d: SweetAlertDialog ->
            d.dismissWithAnimation()
        }
        dlg.show()
    }
}