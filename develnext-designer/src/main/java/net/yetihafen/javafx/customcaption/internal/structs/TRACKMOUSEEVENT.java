package net.yetihafen.javafx.customcaption.internal.structs;

import com.sun.jna.Pointer;
import com.sun.jna.Structure;
import com.sun.jna.platform.win32.WinDef;


@Structure.FieldOrder({"cbSize", "dwFlags", "hwndTrack", "dwHoverTime"})
public class TRACKMOUSEEVENT extends Structure {
    public WinDef.DWORD cbSize;
    public WinDef.DWORD dwFlags;
    public WinDef.HWND hwndTrack;
    public WinDef.DWORD dwHoverTime;

    public TRACKMOUSEEVENT(Pointer p) {
        super(p);
        read();
    }

    public TRACKMOUSEEVENT() {
        super();
    }
}
