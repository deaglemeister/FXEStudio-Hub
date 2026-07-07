package net.yetihafen.javafx.customcaption.internal.libraries;

import com.sun.jna.Library;
import com.sun.jna.Native;
import com.sun.jna.platform.win32.WinDef;
import com.sun.jna.platform.win32.WinNT;
import com.sun.jna.ptr.IntByReference;

public interface DwmApi extends Library {

    DwmApi INSTANCE = Native.load("dwmapi", DwmApi.class);

    // https://docs.microsoft.com/en-us/windows/win32/api/dwmapi/nf-dwmapi-dwmsetwindowattribute
    WinNT.HRESULT DwmSetWindowAttribute(WinDef.HWND hWnd, int dwAttribute, IntByReference pvAttribute, int cbAttribute);
}