package net.yetihafen.javafx.customcaption.internal.structs;

import com.sun.jna.Pointer;
import com.sun.jna.Structure;
import com.sun.jna.platform.win32.WinDef;

@Structure.FieldOrder({"rgrc", "lppos"})
public class NCCALCSIZE_PARAMS extends Structure {
    public WinDef.RECT[] rgrc = new WinDef.RECT[3];
    public Pointer lppos;

    public NCCALCSIZE_PARAMS(Pointer p) {
        super(p);
        read();
    }
}
