package net.yetihafen.javafx.customcaption.internal;

import com.sun.jna.platform.win32.User32;
import com.sun.jna.platform.win32.WinDef;
import com.sun.jna.platform.win32.WinNT;
import com.sun.jna.platform.win32.WinUser;
import com.sun.jna.ptr.IntByReference;
import javafx.geometry.BoundingBox;
import javafx.geometry.Bounds;
import javafx.geometry.Point2D;
import javafx.scene.paint.Color;
import javafx.stage.Stage;
import net.yetihafen.javafx.customcaption.internal.libraries.DwmApi;
import net.yetihafen.javafx.customcaption.internal.libraries.User32Ex;
import net.yetihafen.javafx.customcaption.internal.structs.DWMWINDOWATTRIBUTE;

import java.util.UUID;

import static com.sun.jna.platform.win32.WinUser.SM_CXPADDEDBORDER;
import static com.sun.jna.platform.win32.WinUser.SM_CYSIZEFRAME;

public class NativeUtilities {

    /**
     * *should* return the HWND for the Specified Stage
     * might not, because JavaFX ist stupid and has no way
     * to do this
     * @param stage the Stage
     * @return hopefully the HWND for the correct stage
     */
    public static WinDef.HWND getHwnd(Stage stage) {
        String randomId = UUID.randomUUID().toString();
        String title = stage.getTitle();
        stage.setTitle(randomId);
        WinDef.HWND hWnd = User32.INSTANCE.FindWindow(null, randomId);
        stage.setTitle(title);
        return hWnd;
    }


    /**
     * Enables/disables the Immersive Dark Mode for a specified stage
     * officially only supported (documented) since Win 11 Build 22000
     * @param stage the stage to enable the Dark mode for
     * @param enabled if immersive dark mod should be enabled
     * @return if Immersive Dark Mode could be enabled successfully
     */
    public static boolean setImmersiveDarkMode(Stage stage, boolean enabled) {
        WinDef.HWND hWnd = getHwnd(stage);
        WinNT.HRESULT res = DwmApi.INSTANCE.DwmSetWindowAttribute(hWnd, DWMWINDOWATTRIBUTE.DWMWA_USE_IMMERSIVE_DARK_MODE, new IntByReference(enabled ? 1 : 0), 4);
        return res.longValue() >= 0;
    }

    /**
     * Sets the Caption Color of the specified Stage to the specified Color
     * this does only work since Win 11 Build 22000
     * @param stage the Stage to change the Caption Color
     * @param color the Color to use
     * @return if the change was successful
     */
    public static boolean setCaptionColor(Stage stage, Color color) {
        WinDef.HWND hWnd = getHwnd(stage);
        int red = (int) (color.getRed() * 255);
        int green = (int) (color.getGreen() * 255);
        int blue = (int) (color.getBlue() * 255);
        // win api accepts the colors in reverse order
        int rgb = red + (green << 8) + (blue << 16);
        WinNT.HRESULT res = DwmApi.INSTANCE.DwmSetWindowAttribute(hWnd, DWMWINDOWATTRIBUTE.DWMWA_CAPTION_COLOR, new IntByReference(rgb), 4);
        return res.longValue() >= 0;
    }

    /**
     * sets the caption to the specified color if supported
     * if not supported uses immersive dark mode if color is mostly dark
     * @param stage the stage to modify
     * @param color the color to set the caption
     * @return if the stage was modified
     */
    public static boolean customizeCation(Stage stage, Color color) {
        boolean success = setCaptionColor(stage, color);
        if(!success) {
            int red = (int) (color.getRed() * 255);
            int green = (int) (color.getGreen() * 255);
            int blue = (int) (color.getBlue() * 255);
            int colorSum = red + green + blue;

            boolean dark = colorSum < 255 * 3 / 2;
            success = setImmersiveDarkMode(stage, dark);
        }
        return success;
    }

    public static boolean setBorderColor(Stage stage, Color color) {
        WinDef.HWND hWnd = getHwnd(stage);
        int red = (int) (color.getRed() * 255);
        int green = (int) (color.getGreen() * 255);
        int blue = (int) (color.getBlue() * 255);
        int rgb = red + (green << 8) + (blue << 16);
        WinNT.HRESULT res = DwmApi.INSTANCE.DwmSetWindowAttribute(hWnd, DWMWINDOWATTRIBUTE.DWMWA_BORDER_COLOR, new IntByReference(rgb), 4);
        return res.longValue() >= 0;
    }

    /** DWMWCP_ROUND = 2 — закруглённые углы Windows 11 */
    public static boolean setWindowCornerPreference(Stage stage, int preference) {
        WinDef.HWND hWnd = getHwnd(stage);
        WinNT.HRESULT res = DwmApi.INSTANCE.DwmSetWindowAttribute(
                hWnd, DWMWINDOWATTRIBUTE.DWMWA_WINDOW_CORNER_PREFERENCE, new IntByReference(preference), 4);
        return res.longValue() >= 0;
    }


    public static boolean isMaximized(WinDef.HWND hWnd) {
        long windowStyle = Win32WindowHelper.getWindowStyle(hWnd);
        return (windowStyle & WinUser.WS_MAXIMIZE) == WinUser.WS_MAXIMIZE;
    }

    public static int getResizeHandleHeight(WinDef.HWND hWnd) {
        int dpi = User32Ex.INSTANCE.GetDpiForWindow(hWnd);
        return User32Ex.INSTANCE.GetSystemMetricsForDpi(SM_CXPADDEDBORDER, dpi) +
                User32Ex.INSTANCE.GetSystemMetricsForDpi(SM_CYSIZEFRAME, dpi);
    }

    private static final int CAPTION_BUTTON_WIDTH = 46;
    private static final int CAPTION_BUTTONS_WIDTH = 138;

    /**
     * Экранные координаты кнопок caption через Win32 — совпадают с WM_NCHITTEST.
     * index: 0=min, 1=max, 2=close
     */
    public static Bounds getCaptionButtonScreenBounds(WinDef.HWND hWnd, int index, int captionHeight) {
        if (hWnd == null || index < 0 || index > 2) {
            return null;
        }

        WinDef.RECT clientRect = new WinDef.RECT();
        User32.INSTANCE.GetClientRect(hWnd, clientRect);

        WinDef.POINT origin = new WinDef.POINT(0, 0);
        User32Ex.INSTANCE.ClientToScreen(hWnd, origin);

        int clientWidth = clientRect.right - clientRect.left;
        int left = origin.x + clientWidth - CAPTION_BUTTONS_WIDTH + index * CAPTION_BUTTON_WIDTH;

        return new BoundingBox(left, origin.y, CAPTION_BUTTON_WIDTH, captionHeight);
    }

    /**
     * JavaFX localToScreen и Win32 WM_NCHITTEST могут расходиться по Y (DPI / client frame).
     */
    public static Bounds alignBoundsToWin32Screen(Stage stage, WinDef.HWND hWnd, Bounds fxBounds) {
        if (fxBounds == null || stage == null || hWnd == null || stage.getScene() == null) {
            return fxBounds;
        }

        WinDef.POINT clientOrigin = new WinDef.POINT(0, 0);
        User32Ex.INSTANCE.ClientToScreen(hWnd, clientOrigin);

        Point2D fxOrigin = stage.getScene().getRoot().localToScreen(0, 0);
        if (fxOrigin == null) {
            return fxBounds;
        }

        double offsetX = clientOrigin.x - fxOrigin.getX();
        double offsetY = clientOrigin.y - fxOrigin.getY();

        if (Math.abs(offsetX) < 0.5 && Math.abs(offsetY) < 0.5) {
            return fxBounds;
        }

        return new BoundingBox(
                fxBounds.getMinX() + offsetX,
                fxBounds.getMinY() + offsetY,
                fxBounds.getWidth(),
                fxBounds.getHeight()
        );
    }
}
