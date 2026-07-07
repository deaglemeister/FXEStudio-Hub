package net.yetihafen.javafx.customcaption.internal;

import com.sun.jna.CallbackReference;
import com.sun.jna.Native;
import com.sun.jna.Pointer;
import com.sun.jna.platform.win32.BaseTSD;
import com.sun.jna.platform.win32.User32;
import com.sun.jna.platform.win32.WinDef;
import com.sun.jna.platform.win32.WinUser;

/**
 * Обёртка над User32 для SetWindowLongPtr / CallWindowProc.
 * User32Ex не должен переобъявлять эти методы — иначе JNA ищет неверный экспорт в user32.dll.
 */
final class Win32WindowHelper {

    private Win32WindowHelper() {
    }

    static Pointer setWindowProcedure(WinDef.HWND hWnd, WinUser.WindowProc procedure) {
        Pointer procPtr = CallbackReference.getFunctionPointer(procedure);
        if (Native.POINTER_SIZE == 8) {
            return User32.INSTANCE.SetWindowLongPtr(hWnd, WinUser.GWL_WNDPROC, procPtr);
        }
        int prev = User32.INSTANCE.SetWindowLong(hWnd, WinUser.GWL_WNDPROC, (int) Pointer.nativeValue(procPtr));
        return Pointer.createConstant(prev);
    }

    static void restoreWindowProcedure(WinDef.HWND hWnd, Pointer previousProcedure) {
        if (Native.POINTER_SIZE == 8) {
            User32.INSTANCE.SetWindowLongPtr(hWnd, WinUser.GWL_WNDPROC, previousProcedure);
        } else {
            User32.INSTANCE.SetWindowLong(hWnd, WinUser.GWL_WNDPROC, (int) Pointer.nativeValue(previousProcedure));
        }
    }

    static WinDef.LRESULT callWindowProcedure(Pointer previousProcedure, WinDef.HWND hWnd, int msg,
            WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
        return User32.INSTANCE.CallWindowProc(previousProcedure, hWnd, msg, wParam, lParam);
    }

    static long getWindowStyle(WinDef.HWND hWnd) {
        if (Native.POINTER_SIZE == 8) {
            BaseTSD.LONG_PTR style = User32.INSTANCE.GetWindowLongPtr(hWnd, WinUser.GWL_STYLE);
            return style.longValue();
        }
        return User32.INSTANCE.GetWindowLong(hWnd, WinUser.GWL_STYLE);
    }
}
