package net.yetihafen.javafx.customcaption.internal.libraries;

import com.sun.jna.Native;
import com.sun.jna.platform.win32.User32;
import com.sun.jna.win32.W32APIOptions;
import net.yetihafen.javafx.customcaption.internal.structs.TRACKMOUSEEVENT;

public interface User32Ex extends User32 {

    User32Ex INSTANCE = Native.load("user32", User32Ex.class, W32APIOptions.DEFAULT_OPTIONS);

    // https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-iszoomed
    boolean IsZoomed( HWND hWnd );

    // https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-drawframecontrol
    boolean DrawFrameControl(HDC hdc, RECT rect, int uType, int uState);

    // https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-getdpiforwindow
    int GetDpiForWindow( HWND hwnd );

    // https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-getsystemmetricsfordpi
    int GetSystemMetricsForDpi( int nIndex, int dpi );

    // https://docs.microsoft.com/de-DE/windows/win32/api/winuser/nf-winuser-getdcex
    HDC GetDCEx(HWND hWnd, HRGN hrgnClip, int flags);

    // https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-fillrect
    int FillRect(HDC hdc, RECT lprc, HBRUSH hbr);

    // https://docs.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-adjustwindowrectexfordpi
    boolean AdjustWindowRectExForDpi(RECT lpRect, int dwStyle, boolean bMenu, int dwExStyle, int dpi);

    // https://learn.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-trackmouseevent
    boolean TrackMouseEvent(TRACKMOUSEEVENT lpEventTrack);

    // https://learn.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-screentoclient
    boolean ScreenToClient(HWND hWnd, POINT lpPoint);

    // https://learn.microsoft.com/en-us/windows/win32/api/winuser/nf-winuser-clienttoscreen
    boolean ClientToScreen(HWND hWnd, POINT lpPoint);
}
